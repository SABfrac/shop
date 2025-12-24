<?php

namespace app\services\VendorProduct;

use app\commands\RabbitMqController;
use app\helper\DataNormalizer;
use app\models\{Attributes, Brands, Categories, CategoryAttributeOption, GlobalProducts, Offers, ProductSkus};
use app\services\ProductSkuVariantHashBuilder;
use Yii;
use yii\base\Component;
use yii\db\{Exception, Transaction};
use app\controllers\OffersController;




/**
 * Сервис для ручного ввода/обновления товаров продавцом (GlobalProduct, SKU, Offer)
 */
class VendorProductManagementService extends Component
{
    /**
     * Создаёт или находит существующий GlobalProduct и, опционально, связанный SKU и Offer.
     *
     * @param array $input Данные от формы.
     *                    [
     *                      'category_id' => int,
     *                      'brand_id' => ?int,
     *                      'product_name' => string,
     *                      'gtin' => ?string,
     *                      'model_number' => ?string,
     *                      'non_variant_attributes' => [
     *                          'attribute_code' => 'value',
     *                          ...
     *                      ],
     *                      'variant_attributes' => [
     *                          'attribute_code' => 'value',
     *                          ...
     *                      ],
     *                      'offer_data' => [
     *                          'vendor_sku' => ?string,
     *                          'price' => float,
     *                          'stock' => int,
     *                          'warranty' => ?int,
     *                          'condition' => string,
     *                          'status' => int,
     *                      ]
     *                    ]
     * @param int $vendorId ID продавца
     * @return array ['global_product_id' => int, 'sku_id' => int, 'offer_id' => int]
     * @throws Exception
     */
    public function createOrUpdateGlobalProductAndSku(array $input, int $vendorId): array
    {
        $categoryId = $input['category_id'];
        $brandId = $input['brand_id'] ?? null;
        $productName = $input['product_name'];
        $gtin = $input['gtin'] ?? null;
        $modelNumber = $input['model_number'] ?? null;
        $nonVariantAttrs = $input['non_variant_attributes'] ?? [];
        $variantAttrs = $input['variant_attributes'] ?? [];
        $offerData = $input['offer_data'] ?? [];

        $db = Yii::$app->db;
        $transaction = $db->beginTransaction(Transaction::READ_COMMITTED);

        try {
            // 1. Валидация и нормализация не-вариативных атрибутов
            $nonVariantAttrsStructured = $this->mapAndValidateNonVariantAttributes($nonVariantAttrs, $categoryId);

            // 2. Нормализация и генерация match_key
            $brandName = $brandId ? $this->getBrandName($brandId) : null;
            $canonicalName = DataNormalizer::mathKeyNormalizer($productName, $brandName);
            $matchKey = DataNormalizer::buildMatchKeyForGlobalProduct($productName, $brandName, $categoryId);

            // 3. Поиск существующего GlobalProduct
            $globalProduct = $this->findGlobalProduct($matchKey, $gtin, $modelNumber, $brandId, $categoryId, $canonicalName);

            if (!$globalProduct) {
                // 4. Создание нового GlobalProduct
                $globalProduct = new GlobalProducts();
                $globalProduct->canonical_name = $productName;
                $globalProduct->canonical_name_normalized = $canonicalName;
                $globalProduct->category_id = $categoryId;
                $globalProduct->brand_id = $brandId;
                $globalProduct->gtin = $gtin;
                $globalProduct->model_number = $modelNumber;
                $globalProduct->match_key = $matchKey;
                $globalProduct->attributes_json = $nonVariantAttrsStructured; // Сохраняем не-вариативные атрибуты

                if (!$globalProduct->save()) {
                    throw new Exception('Ошибка сохранения GlobalProduct: ' . json_encode($globalProduct->errors));
                }
            } else {
                // 4.1. Если GlobalProduct найден, обновляем его не-вариативные атрибуты, если они изменились
                $existingNonVariantAttrs = $globalProduct->attributes_json ?: [];
                $mergedNonVariantAttrs = array_replace_recursive($existingNonVariantAttrs, $nonVariantAttrsStructured);
                $globalProduct->attributes_json = $mergedNonVariantAttrs;

                if (!$globalProduct->save()) {
                    throw new Exception('Ошибка обновления GlobalProduct: ' . json_encode($globalProduct->errors));
                }
            }

            $globalProductId = $globalProduct->id;

            // 5. Обработка SKU (вариативные атрибуты)
            $skuId = $offerData['sku_id'] ?? null;
            if (!$skuId &&!empty($variantAttrs)) {
                $skuId = $this->createOrUpdateSku($globalProductId, $variantAttrs, $categoryId);
            }

            if ($skuId && empty($variantAttrs)) {
                $existingSku = ProductSkus::findOne(['id' => $skuId, 'global_product_id' => $globalProductId]);
                if (!$existingSku) {
                    // Если SKU 17 принадлежит другому товару (не этому iPhone), сбрасываем ID
                    // или выбрасываем ошибку
                    throw new Exception("SKU ID {$skuId} не соответствует товару {$globalProductId}");
                }
            }

            // 6. Обработка Offer (если данные предоставлены)
            $offerId = null;
            if (!empty($offerData) && $skuId) {
                $offerId = $this->upsertOffer($skuId, $offerData, $vendorId);
            }

            $transaction->commit();

            if ($offerId) {
                Yii::$app->rabbitmq->publishWithRetries(
                    RabbitMqController::QUEUE_INDEX,
                    [
                        ['offer_ids' => [$offerId]]
                    ]

                );
            }

            return [
                'global_product_id' => $globalProductId,
                'sku_id' => $skuId,
                'offer_id' => $offerId,
            ];
        } catch (\Throwable $e) {
            $transaction->rollBack();
            Yii::error([
                'message' => 'VendorProductManagementService::createOrUpdateGlobalProductAndSku failed',
                'exception' => get_class($e),
                'error' => $e->getMessage(),
                'vendor_id' => $vendorId,
                'input' => $input,
                'trace' => $e->getTraceAsString(),
            ], 'vendor-product-error');
            throw $e;
        }
    }

    /**
     * Валидирует и мапит не-вариативные атрибуты на структурированный формат.
     */
    private function mapAndValidateNonVariantAttributes(array $attrs, int $categoryId): array
    {
        $allowedAttributes = $this->getNonVariantAttributes($categoryId);
        $optionMap = $this->getSelectOptions($categoryId, $allowedAttributes);

        $structured = [];
        foreach ($attrs as $code => $value) {
            if (!isset($allowedAttributes[$code])) {
                throw new Exception("Неизвестный не-вариативный атрибут: '{$code}' для категории {$categoryId}");
            }

            $attr = $allowedAttributes[$code];
            $attrId = (int)$attr['id'];
            $attrType = $attr['type'];
            $attrName = $attr['name'];

            if ($attrType === 'select') {
                $key = mb_strtolower(trim((string)$value), 'UTF-8');
                if (!isset($optionMap[$attrId][$key])) {
                    $available = array_keys($optionMap[$attrId] ?? []);
                    throw new Exception(
                        "Не найдена опция '{$value}' для не-вариативного атрибута '{$code}'. " .
                        "Допустимые: " . implode(', ', array_slice($available, 0, 10))
                    );
                }
                $option = $optionMap[$attrId][$key];
                $structured[] = [
                    'name' => $attrName,
                    'code' => $code,
                    'type' => 'select',
                    'value' => $option['value'],
                    'attribute_option_id' => $option['id'],
                ];
            } else {
                // Валидация и нормализация других типов
                if ($attrType === 'integer') {
                    if (!is_numeric($value) || (string)(int)$value !== (string)$value) {
                        throw new Exception("Не-вариативный атрибут '{$code}' должен быть целым числом");
                    }
                    $val = (int)$value;
                } elseif ($attrType === 'float') {
                    if (!is_numeric($value)) {
                        throw new Exception("Не-вариативный атрибут '{$code}' должен быть числом");
                    }
                    $val = (float)$value;
                } elseif ($attrType === 'bool') {
                    $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($bool === null) {
                        throw new Exception("Не-вариативный атрибут '{$code}' должен быть boolean");
                    }
                    $val = $bool ? '1' : '0';
                } else {
                    $val = (string)$value;
                }
                $structured[] = [
                    'name' => $attrName,
                    'code' => $code,
                    'type' => $attrType,
                    'value' => (string)$val,
                ];
            }
        }

        // Проверка обязательных атрибутов
        foreach ($allowedAttributes as $code => $attr) {
            if ($attr['is_required'] && !isset($attrs[$code])) {
                throw new Exception("Отсутствует обязательный не-вариативный атрибут: '{$code}' для категории {$categoryId}");
            }
        }

        return $structured;
    }

    /**
     * Валидирует и мапит вариативные атрибуты на формат для SKU.
     */
    private function mapAndValidateVariantAttributes(array $attrs, int $categoryId): array
    {
        // Используем логику из OfferBulkImportService, но без хеширования для подготовки данных
        $allowedAttributes = $this->getVariantAttributes($categoryId);
        $optionMap = $this->getSelectOptions($categoryId, $allowedAttributes);

        $forHash = [];
        $forStorage = [];
        foreach ($attrs as $code => $value) {
            if (!isset($allowedAttributes[$code])) {
                throw new Exception("Неизвестный вариативный атрибут: '{$code}' для категории {$categoryId}");
            }

            $attr = $allowedAttributes[$code];
            $attrId = (int)$attr['id'];
            $attrType = $attr['type'];
            $attrName = $attr['name'];

            if ($attrType === 'select') {
                $key = mb_strtolower(trim((string)$value), 'UTF-8');
                if (!isset($optionMap[$attrId][$key])) {
                    $available = array_keys($optionMap[$attrId] ?? []);
                    throw new Exception(
                        "Не найдена опция '{$value}' для вариативного атрибута '{$code}'. " .
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
                if ($attrType === 'integer') {
                    if (!is_numeric($value) || (string)(int)$value !== (string)$value) {
                        throw new Exception("Вариативный атрибут '{$code}' должен быть целым числом");
                    }
                    $val = (int)$value;
                } elseif ($attrType === 'float') {
                    if (!is_numeric($value)) {
                        throw new Exception("Вариативный атрибут '{$code}' должен быть числом");
                    }
                    $val = (float)$value;
                } elseif ($attrType === 'bool') {
                    $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($bool === null) {
                        throw new Exception("Вариативный атрибут '{$code}' должен быть boolean");
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

        foreach ($allowedAttributes as $code => $attr) {
            if ($attr['is_required'] && !isset($attrs[$code])) {
                throw new Exception("Отсутствует обязательный вариативный атрибут: '{$code}' для категории {$categoryId}");
            }
        }

        return [
            'forHash' => $forHash,
            'forStorage' => $forStorage,
        ];
    }

    /**
     * Создаёт или находит существующий SKU.
     */
    private function createOrUpdateSku(int $globalProductId, array $variantAttrs, int $categoryId): int
    {
        $mapped = $this->mapAndValidateVariantAttributes($variantAttrs, $categoryId);
        $variantHash = (new ProductSkuVariantHashBuilder())->buildVariantHash($mapped['forHash'])[0];

        $sku = ProductSkus::find()
            ->where([
                'global_product_id' => $globalProductId,
                'variant_hash' => $variantHash,
            ])
            ->one();

        if (!$sku) {
            $sku = new ProductSkus();
            $sku->global_product_id = $globalProductId;
            $sku->variant_hash = $variantHash;
            $sku->variant_values = $mapped['forStorage'];
            $sku->status = ProductSkus::STATUS_ACTIVE; // Или другой статус по умолчанию

            if (!$sku->save()) {
                throw new Exception('Ошибка сохранения SKU: ' . json_encode($sku->errors));
            }
        }

        return $sku->id;
    }

    /**
     * Создаёт или обновляет Offer.
     */
    private function upsertOffer(int $skuId, array $offerData, int $vendorId): int
    {
        // Валидация offerData
        if (!isset($offerData['price']) || !is_numeric($offerData['price']) || $offerData['price'] < 0) {
            throw new Exception('Неверная цена');
        }
        if (!isset($offerData['stock']) || !is_numeric($offerData['stock']) || $offerData['stock'] < 0) {
            throw new Exception('Неверный остаток');
        }
        // Проверьте другие поля (warranty, condition, status) по необходимости

        $offer = Offers::find()
            ->where([
                'vendor_id' => $vendorId,
                'sku_id' => $skuId,
            ])
            ->one();

        if (!$offer) {
            $offer = new Offers();
            $offer->vendor_id = $vendorId;
            $offer->sku_id = $skuId;
        }

        $offer->vendor_sku = $offerData['vendor_sku'] ?? OffersController::generate();
        $offer->price = $offerData['price'];
        $offer->stock = $offerData['stock'];
        $offer->warranty = $offerData['warranty'] ?? null;
        $offer->condition = $offerData['condition'] ?? 'new';
        $offer->status = $offerData['status'] ?? Offers::STATUS_ACTIVE;
        $offer->sort_order = $offerData['sort_order'] ?? 0;

        if (!$offer->save()) {
            throw new Exception('Ошибка сохранения Offer: ' . json_encode($offer->errors));
        }


        return $offer->id;
    }

    /**
     * Ищет существующий GlobalProduct по критериям.
     */
    private function findGlobalProduct(string $matchKey, ?string $gtin, ?string $modelNumber, ?int $brandId, int $categoryId, string $canonicalName): ?GlobalProducts
    {
        $query = GlobalProducts::find();

        // Приоритет: GTIN -> Model+Brand -> MatchKey -> CanonicalName
        if ($gtin) {
            $query->orWhere(['gtin' => $gtin]);
        }
        if ($modelNumber && $brandId) {
            $query->orWhere([
                'and',
                ['brand_id' => $brandId],
                ['model_number' => $modelNumber]
            ]);
        }
        $query->orWhere(['match_key' => $matchKey]);

        $canonicalKey = $categoryId . '|' . $canonicalName;
        $query->orWhere([
            'and',
            ['category_id' => $categoryId],
            ['canonical_name_normalized' => $canonicalName]
        ]);

        return $query->one();
    }

    /**
     * Получает не-вариативные атрибуты для категории (кеширование можно добавить).
     */
    private function getNonVariantAttributes(int $categoryId): array
    {
        return Attributes::find()
            ->select(['id', 'name', 'type', 'is_required'])
            ->innerJoin(
                ['ca' => 'category_attributes'],
                'ca.attribute_id = attributes.id AND ca.category_id = :catId AND ca.is_variant = false',
                [':catId' => $categoryId]
            )
            ->indexBy('name')
            ->asArray()
            ->all();
    }

    /**
     * Получает вариативные атрибуты для категории (кеширование можно добавить).
     */
    private function getVariantAttributes(int $categoryId): array
    {
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
    }

    /**
     * Получает опции для select-атрибутов (кеширование можно добавить).
     */
    private function getSelectOptions(int $categoryId, array $attributes): array
    {
        $selectAttributeIds = [];
        foreach ($attributes as $attr) {
            if ($attr['type'] === 'select') {
                $selectAttributeIds[] = $attr['id'];
            }
        }
        if (empty($selectAttributeIds)) {
            return [];
        }

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
    }

    private function getBrandName(int $brandId): ?string
    {
        $brand = Brands::findOne($brandId);
        return $brand ? $brand->name : null;
    }


}