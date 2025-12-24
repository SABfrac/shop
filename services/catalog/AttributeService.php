<?php

namespace app\services\catalog;

use app\models\Attributes;
use app\models\CategoryAttributeOption;
use app\models\Categories;
use Yii;

/**
 * Сервис для работы с атрибутами.
 */
class AttributeService
{
    // Кэширование на уровне экземпляра сервиса
    private array $variantAttributesCache = [];
    private array $selectOptionsCache = [];

    public function __construct()
    {
        // Кэш TTL можно вынести в конфигурацию
    }

    /**
     * Маппинг атрибутов фида в структурированный формат для хеширования и хранения.
     * Использует кэширование для схемы и опций.
     *
     * @param array $feedAttrs ['attr_name' => 'value', ...]
     * @param int $categoryId
     * @return array ['forHash' => [...], 'forStorage' => [...]]
     * @throws \InvalidArgumentException
     */
    public function mapFeedAttributesToStructured(array $feedAttrs, int $categoryId): array
    {
        // Загрузка разрешенных атрибутов (кэшируется)
        $allowedAttributes = $this->getCachedVariantAttributes($categoryId);

        // Загрузка опций для select-атрибутов (кэшируется)
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

    // Методы кэширования
    public function getCachedVariantAttributes(int $categoryId): array
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
                3600 // CACHE_TTL
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
                3600 // CACHE_TTL
            );
        }
        return $this->selectOptionsCache[$cacheKey];
    }
}