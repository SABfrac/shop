<?php

namespace app\services\offer;

use app\controllers\OffersController;
use app\helper\DataNormalizer;
use app\models\{Attributes, Brands, Categories, CategoryAttributeOption, GlobalProducts, Offers, ProductSkus};
use app\services\ProductSkuVariantHashBuilder;
use Yii;
use yii\base\Component;
use yii\db\{Exception, Transaction};


/**
 * Cервис массового импорта офферов
 *
 *
 * - Кэширование категорий и атрибутов на уровне экземпляра
 * - Bulk операции
 * - Дедупликация на всех уровнях
 * - Детальный мониторинг производительности
 * - Батчинг для больших объемов
 * - Минимизация запросов к БД
 *
 */
class OfferBulkImportService extends Component
{
    // ========== КОНФИГУРАЦИЯ ==========

    /** @var int Размер батча для вставки global_products */
    private const BATCH_SIZE_GP = 500;

    /** @var int Размер батча для вставки SKUs */
    private const BATCH_SIZE_SKU = 500;

    /** @var int Размер батча для вставки офферов */
    private const BATCH_SIZE_OFFERS = 500;

    /** @var int Время жизни кэша (сек) */
    private const CACHE_TTL = 3600;

    // ========== КЭШИ НА УРОВНЕ ЭКЗЕМПЛЯРА ==========

    /** @var array Кэш категорий */
    private array $categoryCache = [];

    /** @var array Кэш схем атрибутов */
    private array $attributeSchemaCache = [];

    /** @var array Кэш вариантных атрибутов */
    private array $variantAttributesCache = [];

    /** @var array Кэш опций select-атрибутов */
    private array $selectOptionsCache = [];

    // ========== МЕТРИКИ ==========

    /** @var array Метрики производительности */
    private array $metrics = [];

    /**
     * Импортирует чанк строк из фида
     *
     * @param int $vendorId ID вендора
     * @param array $rows Строки фида
     * @param int $categoryId ID категории
     * @param int|null $reportId ID отчета (опционально)
     * @return array Результат импорта
     */
    public function importChunk(int $vendorId, array $rows, int $categoryId, ?int $reportId = null): array {
        if (empty($rows)) {
            return [
                'success' => 0,
                'errors' => [],
                'offer_ids' => [],
                'metrics' => []
            ];
        }

        // Начало отслеживания метрик
        $this->startMetric('total', count($rows));

        try {
            // ===== ШАГ 1: ЗАГРУЗКА КАТЕГОРИИ И СХЕМЫ =====
            $this->startMetric('load_category');

            $category = $this->getCachedCategory($categoryId);
            if (!$category) {
                throw new \InvalidArgumentException("Категория {$categoryId} не найдена");
            }

            $allowedAttributeCodes = $this->getCachedAttributeSchema($categoryId, $category);
            $allowedAttributeCodesSet = array_flip($allowedAttributeCodes);

            $this->endMetric('load_category');

            // ===== ШАГ 2: ВАЛИДАЦИЯ И НОРМАЛИЗАЦИЯ =====
            $this->startMetric('validation');

            [$validRows, $errors] = $this->validateAndNormalizeRows(
                $rows,
                $categoryId,
                $allowedAttributeCodesSet
            );

            $this->endMetric('validation');

            if (empty($validRows)) {
                $this->endMetric('total');
                return [
                    'success' => 0,
                    'errors' => $errors,
                    'offer_ids' => [],
                    'metrics' => $this->getMetrics()
                ];
            }

            // ===== ШАГ 3: ТРАНЗАКЦИЯ ДЛЯ ОСНОВНЫХ ОПЕРАЦИЙ =====
            $db = Yii::$app->db;
            $transaction = $db->beginTransaction(Transaction::READ_COMMITTED);

            try {
                // ===== ШАГ 4: ОБРАБОТКА БРЕНДОВ =====
                $this->startMetric('ensure_brands');
                $brands = $this->ensureBrands($validRows);
                $this->endMetric('ensure_brands');

                // ===== ШАГ 5: СОЗДАНИЕ/СОПОСТАВЛЕНИЕ GP И ОФФЕРОВ =====
                $this->startMetric('match_and_create');
                $result = $this->matchOrCreateGlobalProductAndUpsertOffers(
                    $validRows,
                    $brands,
                    $vendorId
                );
                $this->endMetric('match_and_create');

                $transaction->commit();

                $this->endMetric('total');

                return [
                    'success' => count($validRows),
                    'errors' => $errors,
                    'offer_ids' => $result['offer_ids'] ?? [],
                    'report_id' => $reportId,
                    'metrics' => $this->getMetrics(),
                ];

            } catch (\Throwable $e) {
                $transaction->rollBack();
                throw $e;
            }

        } catch (\Throwable $e) {
            $this->endMetric('total');

            Yii::error([
                'message' => 'OfferBulkImportService::importChunk failed',
                'exception' => get_class($e),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'vendor_id' => $vendorId,
                'category_id' => $categoryId,
                'report_id' => $reportId,
                'metrics' => $this->getMetrics(),
                'trace' => $e->getTraceAsString(),
            ], 'offer-import-error');

            // Заполняем ошибки для всех строк
            $errors = $errors ?? [];
            if (!isset($errors['global'])) {
                $errors['global'] = $e->getMessage();
            }

            return [
                'success' => 0,
                'errors' => $errors,
                'offer_ids' => [],
                'metrics' => $this->getMetrics(),
            ];
        }
    }

    /**
     * ✅ Валидация и нормализация строк фида
     */
    private function validateAndNormalizeRows(
        array $rows,
        int $categoryId,
        array $allowedAttributeCodesSet
    ): array {
        $validRows = [];
        $errors = [];

        foreach ($rows as $i => $row) {
            try {
                // Базовая валидация
                if (empty($row['sku_code']) && empty($row['barcode'])) {
                    throw new \InvalidArgumentException('Требуется sku_code или barcode');
                }
                if (empty($row['product_name'])) {
                    throw new \InvalidArgumentException('Требуется product_name');
                }
                if (!isset($row['price']) || !is_numeric($row['price']) || $row['price'] < 0) {
                    throw new \InvalidArgumentException('Неверная цена');
                }
                if (!isset($row['stock']) || !is_numeric($row['stock']) || $row['stock'] < 0) {
                    throw new \InvalidArgumentException('Неверный остаток');
                }

                // Извлечение атрибутов
                $attributes = [];
                foreach ($row as $key => $value) {
                    if (isset($allowedAttributeCodesSet[$key]) && $value !== '' && $value !== null) {
                        $attributes[$key] = $value;
                    }
                }

                // Маппинг атрибутов
                $parsed = $this->mapFeedAttributesToStructured($attributes, $categoryId);

                // Построение хеша варианта
                [$variantHash] = (new ProductSkuVariantHashBuilder())->buildVariantHash($parsed['forHash']);

                $validRows[] = [
                    'vendor_sku' => trim($row['sku_code'] ?? $row['barcode'] ??  OffersController::generate()),
                    'product_name' => $row['product_name'],
                    'brand_key' => DataNormalizer::normalizer($row['brand'] ?? null),
                    'price' => (float)$row['price'],
                    'stock' => (int)$row['stock'],
                    'condition' => $row['condition'] ?? 'new',
                    'warranty' => !empty($row['warranty']) ? (int)$row['warranty'] : null,
                    'sort_order' => (int)($row['sort_order'] ?? 0),
                    'attributes' => $parsed['forStorage'],
                    'variant_hash' => $variantHash,
                    'category_id' => $categoryId,
                    'gtin' => $row['gtin'] ?? null,
                    'model_number' => $row['model_number'] ?? null,
                ];

            } catch (\Throwable $e) {
                $errors[$i] = $e->getMessage();

                Yii::warning([
                    'message' => 'Row validation failed',
                    'row_index' => $i,
                    'error' => $e->getMessage(),
                ], 'offer-import-validation');
            }
        }

        return [$validRows, $errors];
    }

    /**
     * ✅ Обеспечение существования брендов (оптимизировано)
     */
    private function ensureBrands(array $validRows): array
    {
        // Извлекаем уникальные brand_key
        $uniqueBrandKeys = [];
        foreach ($validRows as $row) {
            if (!empty($row['brand_key'])) {
                $uniqueBrandKeys[$row['brand_key']] = true;
            }
        }

        if (empty($uniqueBrandKeys)) {
            return [];
        }

        $brandKeys = array_filter(array_unique($uniqueBrandKeys));
        $db = Yii::$app->db;
        $tableName = Brands::tableName();

        if (!empty($brandKeys)) {
            $placeholders = [];
            $params = [];
            foreach ($brandKeys as $idx => $brandKey) {
                $placeholders[] = "(:n{$idx})";
                $params[":n{$idx}"] = $brandKey;
            }
            $sql = "
            INSERT INTO {$tableName} (name)
            VALUES " . implode(', ', $placeholders) . "
            ON CONFLICT (name) DO NOTHING
        ";
            $db->createCommand($sql, $params)->execute();
        }

        // Шаг 2: всегда возвращаем актуальные записи
        $existing = Brands::find()
            ->where(['name' => $brandKeys])
            ->indexBy('name')
            ->all();



        return $existing;
    }

    /**
     * ✅ Главная логика сопоставления/создания
     */
    private function matchOrCreateGlobalProductAndUpsertOffers(
        array $validRows,
        array $brands,
        int $vendorId
    ): array {
        if (empty($validRows)) {
            return ['offer_ids' => []];
        }

        // ===== ШАГ 1: ПОДГОТОВКА ДАННЫХ ДЛЯ ПОИСКА =====
        $this->startMetric('prepare_lookup');
        $lookupData = $this->prepareLookupData($validRows, $brands);
        $this->endMetric('prepare_lookup');

        // ===== ШАГ 2: ПРЕДЗАГРУЗКА СУЩЕСТВУЮЩИХ GP =====
        $this->startMetric('preload_gp');
        $existingGlobalProducts = $this->preloadGlobalProducts(
            $lookupData['match_keys'],
            $lookupData['gtins'],
            $lookupData['model_numbers_by_brand'],
            $lookupData['canonical_names_by_category']
        );
        $this->endMetric('preload_gp');

        // ===== ШАГ 3: СОПОСТАВЛЕНИЕ СТРОК С GP =====
        $this->startMetric('match_rows');
        [$matchedRows, $unmatchedRows] = $this->matchRowsWithGlobalProducts(
            $validRows,
            $existingGlobalProducts,
            $brands
        );
        $this->endMetric('match_rows');

        // ===== ШАГ 4: СОЗДАНИЕ НОВЫХ GP =====
        $newGlobalProductIds = [];
        if (!empty($unmatchedRows)) {
            $this->startMetric('insert_gp');
            $newGlobalProductIds = $this->bulkInsertGlobalProducts($unmatchedRows, $brands);
            $this->endMetric('insert_gp');
        }

        // ===== ШАГ 5: ПОДГОТОВКА SKU И OFFERS =====
        $this->startMetric('prepare_sku_offers');
        [$skuDataForUpsert, $offersForUpsert, $skuKeysForFetch] =
            $this->prepareSkuAndOfferDataBulk(
                $matchedRows,
                $unmatchedRows,
                $newGlobalProductIds,
                $brands,
                $vendorId
            );
        $this->endMetric('prepare_sku_offers');

        if (empty($skuDataForUpsert) || empty($offersForUpsert)) {
            return ['offer_ids' => []];
        }

        // ===== ШАГ 6: ПРЕДЗАГРУЗКА СУЩЕСТВУЮЩИХ SKU =====
        $this->startMetric('preload_skus');
        $existingSkus = $this->preloadSkus($skuKeysForFetch);
        $this->endMetric('preload_skus');

        // ===== ШАГ 7: СОЗДАНИЕ НОВЫХ SKU =====
        $this->startMetric('insert_skus');
        $newlyInsertedSkus = $this->bulkInsertSkus(array_values($skuDataForUpsert));
        $this->endMetric('insert_skus');

        // ===== ШАГ 8: ОБЪЕДИНЕНИЕ SKU IDS =====
        $allSkuIds = array_merge($existingSkus, $newlyInsertedSkus);

        // ===== ШАГ 9: СВЯЗЫВАНИЕ SKU_ID С OFFERS =====
        foreach ($offersForUpsert as &$offer) {
            $skuId = $allSkuIds[$offer['_sku_key']] ?? null;
            if (!$skuId) {
                throw new Exception("SKU не найден для ключа: " . $offer['_sku_key']);
            }
            $offer['sku_id'] = $skuId;
            unset($offer['_sku_key']);
        }
        unset($offer);

        // ===== ШАГ 10: UPSERT ОФФЕРОВ =====
        $this->startMetric('upsert_offers');
        $offerIds = $this->upsertOffers($offersForUpsert, $vendorId);
        $this->endMetric('upsert_offers');

        return ['offer_ids' => $offerIds];
    }

    /**
     * ✅ Подготовка данных для поиска GP
     */
    private function prepareLookupData(array $validRows, array $brands): array
    {
        $matchKeys = [];
        $gtins = [];
        $modelNumbersByBrand = [];
        $canonicalNamesByCategory = [];

        foreach ($validRows as $row) {
            $matchKey = DataNormalizer::buildMatchKeyForGlobalProduct(
                $row['product_name'],
                $row['brand_key'],
                $row['category_id']
            );
            $matchKeys[] = $matchKey;

            if (!empty($row['gtin'])) {
                $gtins[] = $row['gtin'];
            }

            if (!empty($row['model_number']) && !empty($row['brand_key']) && isset($brands[$row['brand_key']])) {
                $brandId = $brands[$row['brand_key']]->id;
                $modelNumbersByBrand[$brandId][$row['model_number']] = true;
            }

            $canonicalName = DataNormalizer::mathKeyNormalizer($row['product_name'], $row['brand_key']);
            if ($canonicalName) {
                $canonicalNamesByCategory[$row['category_id']][$canonicalName] = true;
            }
        }

        return [
            'match_keys' => array_unique($matchKeys),
            'gtins' => array_unique($gtins),
            'model_numbers_by_brand' => $modelNumbersByBrand,
            'canonical_names_by_category' => $canonicalNamesByCategory,
        ];
    }

    /**
     * ✅ Предзагрузка global_products
     */
    private function preloadGlobalProducts(
        array $matchKeys,
        array $gtins,
        array $modelNumbersByBrand,
        array $canonicalNamesByCategory
    ): array {
        $conditions = ['or'];

        if (!empty($matchKeys)) {
            $conditions[] = ['match_key' => $matchKeys];
        }

        if (!empty($gtins)) {
            $conditions[] = ['gtin' => $gtins];
        }

        if (!empty($modelNumbersByBrand)) {
            foreach ($modelNumbersByBrand as $brandId => $modelNumbers) {
                $conditions[] = [
                    'and',
                    ['brand_id' => $brandId],
                    ['model_number' => array_keys($modelNumbers)]
                ];
            }
        }

        if (!empty($canonicalNamesByCategory)) {
            foreach ($canonicalNamesByCategory as $categoryId => $names) {
                $conditions[] = [
                    'and',
                    ['category_id' => $categoryId],
                    ['canonical_name_normalized' => array_keys($names)]
                ];
            }
        }

        if (count($conditions) <= 1) {
            return [
                'by_gtin' => [],
                'by_model_brand' => [],
                'by_match_key' => [],
                'by_canonical_name_cat' => []
            ];
        }

        // ✅ ОДИН эффективный запрос вместо загрузки всех записей
        $rows = (new \yii\db\Query())
            ->select([
                'id',
                'match_key',
                'gtin',
                'model_number',
                'brand_id',
                'category_id',
                'canonical_name_normalized'
            ])
            ->from(GlobalProducts::tableName())
            ->where($conditions)
            ->all();

        Yii::info([
            'action' => 'preloadGlobalProducts',
            'rows_loaded' => count($rows),
        ], 'performance');

        return $this->groupGlobalProducts($rows);
    }

    /**
     * ✅ Группировка GP по ключам поиска
     */
    private function groupGlobalProducts(array $rows): array
    {
        $result = [
            'by_gtin' => [],
            'by_model_brand' => [],
            'by_match_key' => [],
            'by_canonical_name_cat' => []
        ];

        foreach ($rows as $row) {
            if (!empty($row['gtin'])) {
                $result['by_gtin'][$row['gtin']] = $row;
            }
            if (!empty($row['model_number']) && !empty($row['brand_id'])) {
                $result['by_model_brand'][$row['brand_id']][$row['model_number']] = $row;
            }
            if (!empty($row['match_key'])) {
                $result['by_match_key'][$row['match_key']] = $row;
            }
            if (!empty($row['canonical_name_normalized']) && !empty($row['category_id'])) {
                $key = $row['category_id'] . '|' . $row['canonical_name_normalized'];
                $result['by_canonical_name_cat'][$key] = $row;
            }
        }

        return $result;
    }

    /**
     * ✅ Сопоставление строк с существующими GP
     */
    private function matchRowsWithGlobalProducts(
        array $validRows,
        array $existingGlobalProducts,
        array $brands
    ): array {
        $matched = [];
        $unmatched = [];

        foreach ($validRows as $row) {
            $globalProduct = null;
            $matchKey = DataNormalizer::buildMatchKeyForGlobalProduct(
                $row['product_name'],
                $row['brand_key'],
                $row['category_id']
            );

            // Приоритет: GTIN -> Model+Brand -> MatchKey -> CanonicalName
            if (!empty($row['gtin']) && isset($existingGlobalProducts['by_gtin'][$row['gtin']])) {
                $globalProduct = $existingGlobalProducts['by_gtin'][$row['gtin']];
            } elseif (!empty($row['model_number']) && !empty($row['brand_key']) && isset($brands[$row['brand_key']])) {
                $brandId = $brands[$row['brand_key']]->id;
                if (isset($existingGlobalProducts['by_model_brand'][$brandId][$row['model_number']])) {
                    $globalProduct = $existingGlobalProducts['by_model_brand'][$brandId][$row['model_number']];
                }
            }

            if (!$globalProduct && isset($existingGlobalProducts['by_match_key'][$matchKey])) {
                $globalProduct = $existingGlobalProducts['by_match_key'][$matchKey];
            }

            if (!$globalProduct) {
                $canonicalName = DataNormalizer::mathKeyNormalizer($row['product_name'], $row['brand_key']);
                if ($canonicalName) {
                    $canonicalKey = $row['category_id'] . '|' . $canonicalName;
                    if (isset($existingGlobalProducts['by_canonical_name_cat'][$canonicalKey])) {
                        $globalProduct = $existingGlobalProducts['by_canonical_name_cat'][$canonicalKey];
                    }
                }
            }

            if ($globalProduct) {
                $matched[] = array_merge($row, ['global_product_id' => $globalProduct['id']]);
            } else {
                $unmatched[] = $row;
            }
        }

        return [$matched, $unmatched];
    }

    /**
     * ✅ Bulk insert global_products (БЕЗ TEMP TABLE!)
     */
    private function bulkInsertGlobalProducts(array $rowsToInsert, array $brands): array
    {
        if (empty($rowsToInsert)) {
            return [];
        }

        // Дедупликация
        $uniqueData = $this->deduplicateGlobalProductData($rowsToInsert, $brands);

        if (empty($uniqueData)) {
            return [];
        }

        $db = Yii::$app->db;
        $gpTable = GlobalProducts::tableName();

        // ✅ Batch insert БЕЗ временной таблицы
        $placeholders = [];
        $params = [];

        foreach ($uniqueData as $idx => $data) {
            $placeholders[] = sprintf(
                '(:cn%d, :bid%d, :cid%d, :gtin%d, :mn%d, :mk%d)',
                $idx, $idx, $idx, $idx, $idx, $idx
            );

            $params[":cn{$idx}"] = $data['canonical_name'];
            $params[":bid{$idx}"] = $data['brand_id'];
            $params[":cid{$idx}"] = $data['category_id'];
            $params[":gtin{$idx}"] = $data['gtin'];
            $params[":mn{$idx}"] = $data['model_number'];
            $params[":mk{$idx}"] = $data['match_key'];
        }

        $sql = "
            INSERT INTO {$gpTable} (canonical_name, brand_id, category_id, gtin, model_number, match_key)
            VALUES " . implode(', ', $placeholders) . "
            ON CONFLICT (match_key) DO NOTHING
        ";

        $db->createCommand($sql, $params)->execute();

        // Получение ID
        $matchKeys = array_column($uniqueData, 'match_key');
        $result = $this->fetchGlobalProductIds($matchKeys, $gpTable);

        Yii::info([
            'action' => 'bulkInsertGlobalProducts',
            'input' => count($rowsToInsert),
            'unique' => count($uniqueData),
            'output' => count($result),
        ], 'performance');

        return $result;
    }

    /**
     * ✅ Дедупликация GP данных
     */
    private function deduplicateGlobalProductData(array $rowsToInsert, array $brands): array
    {
        $uniqueData = [];
        $seen = [];

        foreach ($rowsToInsert as $row) {
            $matchKey = DataNormalizer::buildMatchKeyForGlobalProduct(
                $row['product_name'],
                $row['brand_key'],
                $row['category_id']
            );

            if (isset($seen[$matchKey])) {
                continue;
            }

            $brandId = null;
            if (!empty($row['brand_key']) && isset($brands[$row['brand_key']])) {
                $brandId = $brands[$row['brand_key']]->id;
            }

            $uniqueData[] = [
                'canonical_name' => DataNormalizer::mathKeyNormalizer($row['product_name'], $row['brand_key']),
                'brand_id' => $brandId,
                'category_id' => $row['category_id'],
                'gtin' => $row['gtin'] ?? null,
                'model_number' => $row['model_number'] ?? null,
                'match_key' => $matchKey,
            ];

            $seen[$matchKey] = true;
        }

        return $uniqueData;
    }

    /**
     * ✅ Получение ID GP по match_key
     */
    private function fetchGlobalProductIds(array $matchKeys, string $tableName): array
    {
        if (empty($matchKeys)) {
            return [];
        }

        $rows = (new \yii\db\Query())
            ->select(['id', 'match_key'])
            ->from($tableName)
            ->where(['match_key' => $matchKeys])
            ->all();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['match_key']] = (int)$row['id'];
        }

        return $result;
    }

    /**
     * ✅ Подготовка данных SKU и Offers
     */
    private function prepareSkuAndOfferDataBulk(
        array $matchedRows,
        array $unmatchedRows,
        array $newGlobalProductIds,
        array $brands,
        int $vendorId
    ): array {
        $skuDataForUpsert = [];
        $offersForUpsert = [];
        $skuKeysForFetch = [];

        // Обработка сопоставленных
        foreach ($matchedRows as $row) {
            $this->prepareSingleSkuAndOffer(
                $row,
                $row['global_product_id'],
                $skuDataForUpsert,
                $offersForUpsert,
                $skuKeysForFetch,
                $vendorId
            );
        }

        // Обработка новых GP
        foreach ($unmatchedRows as $row) {
            $matchKey = DataNormalizer::buildMatchKeyForGlobalProduct(
                $row['product_name'],
                $row['brand_key'],
                $row['category_id']
            );

            $globalProductId = $newGlobalProductIds[$matchKey] ?? null;
            if (!$globalProductId) {
                Yii::warning("GP ID не найден для match_key: {$matchKey}");
                continue;
            }

            $this->prepareSingleSkuAndOffer(
                $row,
                $globalProductId,
                $skuDataForUpsert,
                $offersForUpsert,
                $skuKeysForFetch,
                $vendorId
            );
        }

        return [$skuDataForUpsert, $offersForUpsert, $skuKeysForFetch];
    }

    /**
     * ✅ Подготовка одного SKU и Offer
     */
    private function prepareSingleSkuAndOffer(
        array $row,
        int $globalProductId,
        array &$skuDataForUpsert,
        array &$offersForUpsert,
        array &$skuKeysForFetch,
        int $vendorId
    ): void {
        $skuKey = $globalProductId . '|' . $row['variant_hash'];

        if (!isset($skuDataForUpsert[$skuKey])) {
            $skuDataForUpsert[$skuKey] = [
                'global_product_id' => $globalProductId,
                'variant_hash' => $row['variant_hash'],
                'variant_values' => json_encode($row['attributes'], JSON_UNESCAPED_UNICODE),
                'status' => ProductSkus::STATUS_ACTIVE,
            ];
            $skuKeysForFetch[$skuKey] = true;
        }

        $offersForUpsert[] = [
            '_sku_key' => $skuKey,
            'vendor_id' => $vendorId,
            'vendor_sku' => $row['vendor_sku'],
            'price' => $row['price'],
            'stock' => $row['stock'],
            'warranty' => $row['warranty'],
            'condition' => $row['condition'],
            'status' => Offers::STATUS_ACTIVE,
            'sort_order' => $row['sort_order'],
        ];
    }

    /**
     * ✅ Предзагрузка существующих SKU
     */
    private function preloadSkus(array $skuKeys): array
    {
        if (empty($skuKeys)) {
            return [];
        }

        $conditions = ['or'];
        foreach (array_keys($skuKeys) as $skuKey) {
            [$gpId, $vHash] = explode('|', $skuKey, 2);
            $conditions[] = [
                'and',
                ['global_product_id' => (int)$gpId],
                ['variant_hash' => $vHash]
            ];
        }

        $rows = (new \yii\db\Query())
            ->select(['id', 'global_product_id', 'variant_hash'])
            ->from(ProductSkus::tableName())
            ->where($conditions)
            ->all();

        $result = [];
        foreach ($rows as $row) {
            $key = $row['global_product_id'] . '|' . $row['variant_hash'];
            $result[$key] = (int)$row['id'];
        }

        return $result;
    }

    /**
     * ✅ Bulk insert SKUs (БЕЗ TEMP TABLE!)
     */
    private function bulkInsertSkus(array $skuDataToInsert): array
    {
        if (empty($skuDataToInsert)) {
            return [];
        }

        // Дедупликация
        $uniqueSkuData = $this->deduplicateSkuData($skuDataToInsert);

        if (empty($uniqueSkuData)) {
            return [];
        }

        $db = Yii::$app->db;
        $psTable = ProductSkus::tableName();

        // ✅ Batch insert БЕЗ временной таблицы
        $placeholders = [];
        $params = [];

        foreach ($uniqueSkuData as $idx => $item) {
            $placeholders[] = sprintf(
                '(:gp%d, :vh%d, :vv%d::jsonb, :st%d)',
                $idx, $idx, $idx, $idx
            );

            $params[":gp{$idx}"] = $item['global_product_id'];
            $params[":vh{$idx}"] = $item['variant_hash'];
            $params[":vv{$idx}"] = $item['variant_values'];
            $params[":st{$idx}"] = $item['status'];
        }

        $sql = "
            INSERT INTO {$psTable} (global_product_id, variant_hash, variant_values, status)
            VALUES " . implode(', ', $placeholders) . "
            ON CONFLICT (global_product_id, variant_hash) DO NOTHING
        ";

        $db->createCommand($sql, $params)->execute();

        // Получение ID
        $result = $this->fetchSkuIds($uniqueSkuData, $psTable);

        Yii::info([
            'action' => 'bulkInsertSkus',
            'input' => count($skuDataToInsert),
            'unique' => count($uniqueSkuData),
            'output' => count($result),
        ], 'performance');

        return $result;
    }

    /**
     * ✅ Дедупликация SKU
     */
    private function deduplicateSkuData(array $skuData): array
    {
        $unique = [];
        $seen = [];

        foreach ($skuData as $item) {
            $key = $item['global_product_id'] . '|' . $item['variant_hash'];

            if (isset($seen[$key])) {
                continue;
            }

            $unique[] = $item;
            $seen[$key] = true;
        }

        return $unique;
    }

    /**
     * ✅ Получение ID SKU
     */
    private function fetchSkuIds(array $skuData, string $tableName): array
    {
        if (empty($skuData)) {
            return [];
        }

        $conditions = ['or'];
        foreach ($skuData as $item) {
            $conditions[] = [
                'and',
                ['global_product_id' => $item['global_product_id']],
                ['variant_hash' => $item['variant_hash']]
            ];
        }

        $rows = (new \yii\db\Query())
            ->select(['id', 'global_product_id', 'variant_hash'])
            ->from($tableName)
            ->where($conditions)
            ->all();

        $result = [];
        foreach ($rows as $row) {
            $key = $row['global_product_id'] . '|' . $row['variant_hash'];
            $result[$key] = (int)$row['id'];
        }

        return $result;
    }

    /**
     * ✅ Upsert офферов (оптимизировано с дедупликацией)
     */
    private function upsertOffers(array $offers, int $vendorId): array
    {
        if (empty($offers)) {
            return [];
        }

        // Дедупликация по (vendor_id, sku_id)
        $deduplicated = $this->deduplicateOffers($offers);

        // Валидация
        $this->validateOffersStructure($deduplicated);

        $db = Yii::$app->db;
        $tableName = Offers::tableName();

        $valuePlaceholders = [];
        $params = [];

        foreach ($deduplicated as $index => $offer) {
            $valuePlaceholders[] = sprintf(
                '(:vs%d, :si%d, :vi%d, :pr%d, :st%d, :wr%d, :cn%d, :ss%d, :so%d)',
                $index, $index, $index, $index, $index, $index, $index, $index, $index
            );

            $params[":vs{$index}"] = $offer['vendor_sku'];
            $params[":si{$index}"] = $offer['sku_id'];
            $params[":vi{$index}"] = $offer['vendor_id'];
            $params[":pr{$index}"] = $offer['price'];
            $params[":st{$index}"] = $offer['stock'];
            $params[":wr{$index}"] = $offer['warranty'] ?? null;
            $params[":cn{$index}"] = $offer['condition'];
            $params[":ss{$index}"] = $offer['status'];
            $params[":so{$index}"] = $offer['sort_order'];
        }

        $columns = 'vendor_sku, sku_id, vendor_id, price, stock, warranty, condition, status, sort_order';
        $updateSet = implode(', ', [
            'vendor_sku = EXCLUDED.vendor_sku',
            'price = EXCLUDED.price',
            'stock = EXCLUDED.stock',
            'warranty = EXCLUDED.warranty',
            'condition = EXCLUDED.condition',
            'status = EXCLUDED.status',
            'sort_order = EXCLUDED.sort_order',
            'updated_at = NOW()'
        ]);

        $sql = "
            INSERT INTO {$tableName} ({$columns})
            VALUES " . implode(', ', $valuePlaceholders) . "
            ON CONFLICT (vendor_id, sku_id) DO UPDATE SET {$updateSet}
            RETURNING id, vendor_id, sku_id
        ";

        $rows = $db->createCommand($sql, $params)->queryAll();

        // Карта результатов
        $resultMap = [];
        foreach ($rows as $row) {
            $key = $row['vendor_id'] . '|' . $row['sku_id'];
            $resultMap[$key] = (int)$row['id'];
        }

        // Возвращаем в порядке входных данных
        $result = [];
        foreach ($deduplicated as $offer) {
            $key = $offer['vendor_id'] . '|' . $offer['sku_id'];
            $result[] = $resultMap[$key] ?? null;
        }

        return $result;
    }

    /**
     * ✅ Дедупликация офферов
     */
    private function deduplicateOffers(array $offers): array
    {
        $unique = [];
        $seen = [];

        foreach ($offers as $offer) {
            $key = $offer['vendor_id'] . '|' . $offer['sku_id'];

            if (isset($seen[$key])) {
                continue;
            }

            $unique[] = $offer;
            $seen[$key] = true;
        }

        return $unique;
    }

    /**
     * ✅ Валидация структуры офферов
     */
    private function validateOffersStructure(array $offers): void
    {
        $required = ['vendor_sku', 'sku_id', 'vendor_id', 'price', 'stock', 'condition', 'status', 'sort_order'];

        foreach ($offers as $i => $offer) {
            foreach ($required as $field) {
                if (!isset($offer[$field])) {
                    throw new \InvalidArgumentException("Offer #{$i} missing field: {$field}");
                }
            }
        }
    }

    /**
     * ✅ Маппинг атрибутов фида (с кэшированием)
     */
    private function mapFeedAttributesToStructured(array $feedAttrs, int $categoryId): array
    {
        // Загрузка разрешенных атрибутов
        $allowedAttributes = $this->getCachedVariantAttributes($categoryId);

        // Загрузка опций для select-атрибутов
        $optionMap = $this->getCachedSelectOptions($categoryId, $allowedAttributes);

        $forHash = [];
        $forStorage = [];

        foreach ($feedAttrs as $feedName => $value) {
            if (!isset($allowedAttributes[$feedName])) {
                throw new \InvalidArgumentException("Неизвестный атрибут: '{$feedName}'");
            }

            if ($value === '' || $value === null) {
                continue;
            }

            $attr = $allowedAttributes[$feedName];
            $attrId = (int)$attr['id'];
            $attrType = $attr['type'];
            $attrName = $attr['name'];

            $normalizedInput = ($attrType === 'float' || $attrType === 'integer')
                ? str_replace(',', '.', trim((string)$value))
                : $value;

            if ($attrType === 'select') {
                $key = mb_strtolower(trim((string)$normalizedInput), 'UTF-8');

                if (!isset($optionMap[$attrId][$key])) {
                    $available = array_keys($optionMap[$attrId] ?? []);
                    throw new \InvalidArgumentException(
                        "Не найдена опция '{$value}' для атрибута '{$feedName}'. " .
                        "Допустимые: " . implode(', ', array_slice($available, 0, 10))
                    );
                }

                $option = $optionMap[$attrId][$key];

                $forHash[] = [
                    'attribute_id' => $attrId,
                    'type' => 'select',
                    'attribute_option_id' => $option['id'],
                ];

                $forStorage[] = [
                    'name' => $attrName,
                    'value' => $option['value'],
                    'type' => 'select',
                ];

            } else {
                // Обработка не-select типов
                if ($attrType === 'integer') {
                    if (!is_numeric($normalizedInput) || (string)(int)$normalizedInput !== (string)$normalizedInput) {
                        throw new \InvalidArgumentException("Атрибут '{$feedName}' должен быть целым числом");
                    }
                    $val = (int)$normalizedInput;
                } elseif ($attrType === 'float') {
                    if (!is_numeric($normalizedInput)) {
                        throw new \InvalidArgumentException("Атрибут '{$feedName}' должен быть числом");
                    }
                    $val = (float)$normalizedInput;
                } elseif ($attrType === 'bool') {
                    $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($bool === null) {
                        throw new \InvalidArgumentException("Атрибут '{$feedName}' должен быть boolean");
                    }
                    $val = $bool ? '1' : '0';
                } else {
                    $val = (string)$value;
                }

                $forHash[] = match($attrType) {
                    'integer' => ['attribute_id' => $attrId, 'type' => 'integer', 'value_int' => $val],
                    'float'   => ['attribute_id' => $attrId, 'type' => 'float', 'value_float' => $val],
                    'bool'    => ['attribute_id' => $attrId, 'type' => 'bool', 'value_bool' => $val === '1'],
                    default   => ['attribute_id' => $attrId, 'type' => 'string', 'value_string' => $val],
                };

                $forStorage[] = [
                    'name' => $attrName,
                    'value' => (string)$val,
                    'type' => $attrType,
                ];
            }
        }

        // Проверка обязательных атрибутов
        foreach ($allowedAttributes as $name => $attr) {
            if ($attr['is_required'] && !isset($feedAttrs[$name])) {
                throw new \InvalidArgumentException("Отсутствует обязательный атрибут: '{$name}'");
            }
        }

        return [
            'forHash' => $forHash,
            'forStorage' => $forStorage,
        ];
    }

    // ========== МЕТОДЫ КЭШИРОВАНИЯ ==========

    private function getCachedCategory(int $categoryId): ?Categories
    {
        if (!isset($this->categoryCache[$categoryId])) {
            $this->categoryCache[$categoryId] = Categories::findOne($categoryId);
        }
        return $this->categoryCache[$categoryId];
    }

    private function getCachedAttributeSchema(int $categoryId, Categories $category): array
    {
        if (!isset($this->attributeSchemaCache[$categoryId])) {
            $cacheKey = "category_schema:{$categoryId}";

            $this->attributeSchemaCache[$categoryId] = Yii::$app->cache->getOrSet(
                $cacheKey,
                fn() => $category->getFeedAttributeSchema(),
                self::CACHE_TTL
            );
        }
        return $this->attributeSchemaCache[$categoryId];
    }

    private function getCachedVariantAttributes(int $categoryId): array
    {
        if (!isset($this->variantAttributesCache[$categoryId])) {
            $cacheKey = "category_variant_attrs:{$categoryId}";

            $this->variantAttributesCache[$categoryId] = Yii::$app->cache->getOrSet(
                $cacheKey,
                function () use ($categoryId) {
                    return Attributes::find()
                        ->select(['id', 'name', 'type', 'is_required'])
                        ->innerJoin(
                            ['ca' => 'category_attributes'],
                            'ca.attribute_id = attributes.id AND ca.category_id = :catId AND ca.is_variant = true',
                            [':catId' => $categoryId]
                        )
                        ->indexBy('name')
                        ->asArray()
                        ->all();
                },
                self::CACHE_TTL
            );
        }
        return $this->variantAttributesCache[$categoryId];
    }

    private function getCachedSelectOptions(int $categoryId, array $allowedAttributes): array
    {
        $selectAttributeIds = [];
        foreach ($allowedAttributes as $attr) {
            if ($attr['type'] === 'select') {
                $selectAttributeIds[] = $attr['id'];
            }
        }

        if (empty($selectAttributeIds)) {
            return [];
        }

        sort($selectAttributeIds, SORT_NUMERIC);
        $cacheKey = "select_options:{$categoryId}:" . md5(implode(',', $selectAttributeIds));

        if (!isset($this->selectOptionsCache[$cacheKey])) {
            $this->selectOptionsCache[$cacheKey] = Yii::$app->cache->getOrSet(
                $cacheKey,
                function () use ($categoryId, $selectAttributeIds) {
                    $options = CategoryAttributeOption::find()
                        ->select(['attribute_id', 'value', 'id'])
                        ->where([
                            'category_id' => $categoryId,
                            'attribute_id' => $selectAttributeIds,
                        ])
                        ->asArray()
                        ->all();

                    $map = [];
                    foreach ($options as $opt) {
                        $key = mb_strtolower(trim($opt['value']), 'UTF-8');
                        $map[$opt['attribute_id']][$key] = [
                            'id' => (int)$opt['id'],
                            'value' => $opt['value'],
                        ];
                    }
                    return $map;
                },
                self::CACHE_TTL
            );
        }

        return $this->selectOptionsCache[$cacheKey];
    }

    // ========== МЕТРИКИ ==========

    private function startMetric(string $key, ?int $count = null): void
    {
        $this->metrics[$key] = [
            'start' => microtime(true),
            'count' => $count,
        ];
    }

    private function endMetric(string $key): void
    {
        if (isset($this->metrics[$key]['start'])) {
            $duration = microtime(true) - $this->metrics[$key]['start'];
            $this->metrics[$key]['duration'] = round($duration, 3);

            if (isset($this->metrics[$key]['count']) && $this->metrics[$key]['count'] > 0) {
                $this->metrics[$key]['throughput'] = round(
                    $this->metrics[$key]['count'] / max($duration, 0.001),
                    2
                );
            }

            unset($this->metrics[$key]['start']);
        }
    }

    private function getMetrics(): array
    {
        return $this->metrics;
    }


}




