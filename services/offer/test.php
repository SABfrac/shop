<?php

namespace app\services\offer;
use app\models\Categories;
use app\models\Offers;
use app\models\ProductSkus;
use app\services\catalog\AttributeService;
use app\services\catalog\BrandService;
use app\services\catalog\DataNormalizerService;
use app\services\catalog\GlobalProductService;
use app\services\catalog\OfferService;
use app\services\catalog\SkuService;
use app\services\ProductSkuVariantHashBuilder;
use Yii;
use yii\base\Component;
use yii\db\Transaction;
use app\controllers\OffersController;
/**
 * Обновленный сервис для импорта фидов.
 * Теперь делегирует основную логику другим сервисам.
 */
class test extends Component
{
    // ========== КОНФИГУРАЦИЯ ==========
    private const BATCH_SIZE_GP = 500;
    private const BATCH_SIZE_SKU = 500;
    private const BATCH_SIZE_OFFERS = 500;

    // ========== СЕРВИСЫ ==========
    private BrandService $brandService;
    private GlobalProductService $globalProductService;
    private SkuService $skuService;
    private OfferService $offerService;
    private AttributeService $attributeService;
    private DataNormalizerService $dataNormalizerService;
    private ProductSkuVariantHashBuilder $productSkuVariantHashBuilder;


    // ========== КЭШИ НА УРОВНЕ ЭКЗЕМПЛЯРА ==========
    private array $categoryCache = [];

    // ========== МЕТРИКИ ==========
    private array $metrics = [];

    public function __construct(
        BrandService $brandService,
        GlobalProductService $globalProductService,
        SkuService $skuService,
        OfferService $offerService,
        AttributeService $attributeService,
        DataNormalizerService $dataNormalizerService,
        ProductSkuVariantHashBuilder $productSkuVariantHashBuilder,

        $config = []
    ) {
        $this->brandService = $brandService;
        $this->globalProductService = $globalProductService;
        $this->skuService = $skuService;
        $this->offerService = $offerService;
        $this->attributeService = $attributeService;
        $this->dataNormalizerService = $dataNormalizerService;
        $this->productSkuVariantHashBuilder=$productSkuVariantHashBuilder;
        parent::__construct($config);
    }

    public function importChunk(int $vendorId, array $rows, int $categoryId, ?int $reportId = null): array
    {
        if (empty($rows)) {
            return [
                'success' => 0,
                'errors' => [],
                'offer_ids' => [],
                'metrics' => []
            ];
        }

        $this->startMetric('total', count($rows));

        try {
            // ===== ШАГ 1: ЗАГРУЗКА КАТЕГОРИИ И СХЕМЫ =====
            $this->startMetric('load_category');
            $category = $this->getCachedCategory($categoryId);
            if (!$category) {
                throw new \InvalidArgumentException("Категория {$categoryId} не найдена");
            }
            // Предположим, что схема атрибутов загружается где-то в другом месте или не нужна в этом виде
            // $allowedAttributeCodes = $this->getCachedAttributeSchema($categoryId, $category);
            // $allowedAttributeCodesSet = array_flip($allowedAttributeCodes);
            $this->endMetric('load_category');

            // ===== ШАГ 2: ВАЛИДАЦИЯ И НОРМАЛИЗАЦИЯ =====
            $this->startMetric('validation');
            [$validRows, $errors] = $this->validateAndNormalizeRows(
                $rows,
                $categoryId,
                $reportId,
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


                $this->endMetric('total');

                return [
                    'success' => count($validRows),
                    'errors' => $errors,
                    'offer_ids' => $result['offer_ids'] ?? [],
                    'report_id' => $reportId,
                    'metrics' => $this->getMetrics(),
                ];
            } catch (\Throwable $e) {

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

    private function validateAndNormalizeRows(
        array $rows,
        int $categoryId,
        int $reportId,
    ): array {
        $validRows = [];
        $errors = [];

        $allowedVariantAttributes = $this->attributeService->getCachedVariantAttributes($categoryId);
        $allowedAttributeCodesSet = array_flip(array_keys($allowedVariantAttributes)); // Быстрая проверка

        foreach ($rows as $i => $row) {
            try {
                if (empty($row['sku_code'])) {
                    throw new \InvalidArgumentException('Требуется vendor_sku (поле sku_code)');
                }

                if (empty($row['product_name'])) {
                    throw new \InvalidArgumentException('Требуется product_name');
                }
                if (!isset($row['price']) || !is_numeric($row['price']) || $row['price'] < 0) {
                    throw new \InvalidArgumentException('Неверная цена ,не должна быть отрицательной');
                }
                if (!isset($row['stock']) || !is_numeric($row['stock']) || $row['stock'] < 0) {
                    throw new \InvalidArgumentException('Неверный остаток,не должна быть отрицательной');
                }

                // Извлечение атрибутов (предполагается, что они уже известны из схемы)
                $attributes = [];
                foreach ($row as $key => $value) {
                    // Предполагаем, что все ключи из $row - это атрибуты
                    if (isset($allowedAttributeCodesSet[$key]) && $value !== '' && $value !== null) {
                        $attributes[$key] = $value;
                    }
                }

                // Маппинг атрибутов через сервис
                $parsed = $this->attributeService->mapFeedAttributesToStructured($attributes, $categoryId);

                // Построение хеша варианта
                [$variantHash] = $this->productSkuVariantHashBuilder->buildVariantHash($parsed['forHash']);

                $validRows[] = [
                    'vendor_sku' => strtolower(trim($row['sku_code'] ?? OffersController::generate())),
                    'product_name' => $row['product_name'],
                    'brand_key' => $this->dataNormalizerService->normalizer($row['brand'] ?? null),
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
                $sku = $row['sku_code'] ?? 'unknown';
                $this->reportRowError($reportId, $i, $sku, $e->getMessage());
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

    private function ensureBrands(array $validRows): array
    {
        $uniqueBrandKeys = [];
        foreach ($validRows as $row) {
            if (!empty($row['brand_key'])) {
                $uniqueBrandKeys[$row['brand_key']] = true;
            }
        }

        if (empty($uniqueBrandKeys)) {
            return [];
        }

        $brandKeys = array_keys($uniqueBrandKeys);
        return $this->brandService->ensureBrands($brandKeys);
    }

    private function matchOrCreateGlobalProductAndUpsertOffers(
        array $validRows,
        array $brands,
        int $vendorId
    ): array
    {
        if (empty($validRows)) {
            return ['offer_ids' => []];
        }

        // ===== ШАГ 1: ПОДГОТОВКА ДАННЫХ ДЛЯ ПОИСКА =====
        $this->startMetric('prepare_lookup');
        $lookupData = $this->prepareLookupData($validRows, $brands);
        $this->endMetric('prepare_lookup');

        // ===== ШАГ 2: ПРЕДЗАГРУЗКА СУЩЕСТВУЮЩИХ GP =====
        $this->startMetric('preload_gp');
        $existingGlobalProducts = $this->globalProductService->preloadGlobalProducts(
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
        $existingSkus = $this->skuService->preloadSkus($skuKeysForFetch);
        $this->endMetric('preload_skus');

        // ===== ШАГ 7: СОЗДАНИЕ НОВЫХ SKU =====
        $this->startMetric('insert_skus');
        $newlyInsertedSkus = $this->skuService->bulkInsertSkus(array_values($skuDataForUpsert));
        $this->endMetric('insert_skus');

        // ===== ШАГ 8: ОБЪЕДИНЕНИЕ SKU IDS =====
        $allSkuIds = array_merge($existingSkus, $newlyInsertedSkus);

        // ===== ШАГ 9: СВЯЗЫВАНИЕ SKU_ID С OFFERS =====
        foreach ($offersForUpsert as &$offer) {
            $skuId = $allSkuIds[$offer['_sku_key']] ?? null;
            if (!$skuId) {
                throw new \Exception("SKU не найден для ключа: " . $offer['_sku_key']);
            }
            $offer['sku_id'] = $skuId;
            unset($offer['_sku_key']);
        }
        unset($offer);
        // ===== ШАГ 10: UPSERT ОФФЕРОВ =====


        $this->startMetric('upsert_offers');
        $offerIds = $this->offerService->upsertOffers($offersForUpsert, $vendorId);
        $this->endMetric('upsert_offers');

            return ['offer_ids' => $offerIds];

    }

    // Остальные private методы остаются теми же, но используют сервисы
    // prepareLookupData, matchRowsWithGlobalProducts, bulkInsertGlobalProducts, prepareSkuAndOfferDataBulk, prepareSingleSkuAndOffer, deduplicateGlobalProductData
  // (реализация этих методов остается аналогичной, но вызовы методов вроде $this->preloadGlobalProduменяются на $this->globalProductService->preloadGlobalProducts)

    private function prepareLookupData(array $validRows, array $brands): array
    {
        $matchKeys = [];
        $gtins = [];
        $modelNumbersByBrand = [];
        $canonicalNamesByCategory = [];

        foreach ($validRows as $row) {
            $matchKey = $this->dataNormalizerService->buildMatchKeyForGlobalProduct(
                $row['product_name'],
                $row['brand_key'],
                $row['category_id']
            );
            $matchKeys[] = $matchKey;
            if (!empty($row['gtin'])) {
                $gtins[] = $row['gtin'];
            }
            if (!empty($row['model_number']) && !empty($row['brand_key']) && isset($brands[$row['brand_key']])) {
                $brandId = $brands[$row['brand_key']] ?? null;
                $modelNumbersByBrand[$brandId][$row['model_number']] = true;
            }
            $canonicalName = $this->dataNormalizerService->mathKeyNormalizer($row['product_name'], $row['brand_key']);
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

    private function matchRowsWithGlobalProducts(
        array $validRows,
        array $existingGlobalProducts,
        array $brands
    ): array {
        $matched = [];
        $unmatched = [];

        foreach ($validRows as $row) {
            $globalProduct = null;
            $matchKey = $this->dataNormalizerService->buildMatchKeyForGlobalProduct(
                $row['product_name'],
                $row['brand_key'],
                $row['category_id']
            );

            if (!empty($row['gtin']) && isset($existingGlobalProducts['by_gtin'][$row['gtin']])) {
                $globalProduct = $existingGlobalProducts['by_gtin'][$row['gtin']];
            } elseif (!empty($row['model_number']) && !empty($row['brand_key']) && isset($brands[$row['brand_key']])) {
                $brandId = $brands[$row['brand_key']] ?? null;
                if (isset($existingGlobalProducts['by_model_brand'][$brandId][$row['model_number']])) {
                    $globalProduct = $existingGlobalProducts['by_model_brand'][$brandId][$row['model_number']];
                }
            }
            if (!$globalProduct && isset($existingGlobalProducts['by_match_key'][$matchKey])) {
                $globalProduct = $existingGlobalProducts['by_match_key'][$matchKey];
            }
            if (!$globalProduct) {
                $canonicalName = $this->dataNormalizerService->mathKeyNormalizer($row['product_name'], $row['brand_key']);
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

    private function bulkInsertGlobalProducts(array $rowsToInsert, array $brands): array
    {
        if (empty($rowsToInsert)) {
            return [];
        }

        $uniqueData = $this->deduplicateGlobalProductData($rowsToInsert, $brands);
        if (empty($uniqueData)) {
            return [];
        }

        return $this->globalProductService->bulkInsertGlobalProducts($uniqueData);
    }

    private function deduplicateGlobalProductData(array $rowsToInsert, array $brands): array
    {
        $uniqueData = [];
        $seen = [];

        foreach ($rowsToInsert as $row) {

            $brandKey = $row['brand_key'] ?? 'NULL';
            $hasBrand = !empty($brandKey) && isset($brands[$brandKey]);
            Yii::info([
                'brand_key' => $brandKey,
                'brand_found' => $hasBrand,
                'available_brands' => array_keys($brands),
            ], 'debug-brand-match');

            $matchKey = $this->dataNormalizerService->buildMatchKeyForGlobalProduct(
                $row['product_name'],
                $row['brand_key'],
                $row['category_id']
            );
            if (isset($seen[$matchKey])) {
                continue;
            }
            $brandId = null;
            if (!empty($row['brand_key']) && isset($brands[$row['brand_key']])) {
                $brandId = (int)$brands[$row['brand_key']];
            }
            $uniqueData[] = [
                'canonical_name' => $row['product_name'],
                'canonical_name_normalized' => $this->dataNormalizerService->mathKeyNormalizer(
                    $row['product_name'],
                    $row['brand_key']
                ) ?: '',
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

        foreach ($unmatchedRows as $row) {
            $matchKey = $this->dataNormalizerService->buildMatchKeyForGlobalProduct(
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

    // ========== МЕТОДЫ КЭШИРОВАНИЯ ==========
    private function getCachedCategory(int $categoryId): ?Categories
    {
        if (!isset($this->categoryCache[$categoryId])) {
            $this->categoryCache[$categoryId] = Categories::findOne($categoryId);
        }
        return $this->categoryCache[$categoryId];
    }

  //  метод для регистрации ошибки
    private function reportRowError(int $reportId, int $rowIndex, string $sku, string $message, array $rawData = []): void
    {
        // Формируем структуру ошибки для продавца
        $errorData = [
            'line' => $rowIndex,
            'sku'  => $sku,
            'msg'  => $message,
            // Если очень нужно позволить скачать CSV с исправлением,
            // можно сохранять ID строки или ключевые поля, но лучше не всю строку, если она огромная.
            // Для примера сохраним sku и message.
        ];

        // RPUSH атомарен и быстр
        Yii::$app->redis->executeCommand('RPUSH', [
            "feed:errors:{$reportId}",
            json_encode($errorData, JSON_UNESCAPED_UNICODE)
        ]);
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