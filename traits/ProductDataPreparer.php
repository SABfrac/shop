<?php

namespace app\traits;

use app\models\Products;
use yii\caching\TagDependency;
use yii\base\Component;
use yii;


trait ProductDataPreparer
{
    protected function prepareProductData(Products $product)
    {
        // сюда Product должен прилетать c Жадной загрузка всех необходимых данных одним запросом

        $data = array_merge(
            $this->prepareBasic($product),
            $this->prepareCategory($product),
            $this->prepareBrand($product),
            $this->prepareFlatAttributes($product),
            $this->prepareAttributes($product)
        );

        return $data;
    }


    protected function prepareBasic(Products $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description ?? '',
            'price' => (float)$product->price,
            'status' => (int)$product->status,
            'quantity' => $product->quantity,
        ];
    }

    protected function prepareCategory(Products $product): array
    {
        if ($product->category) {
            return [
                'category' => [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                ]
            ];
        }

        return [];
    }

    protected function prepareBrand(Products $product): array
    {
        if ($product->brand) {
            return [
                'brand' => [
                    'id' => $product->brand->id,
                    'name' => $product->brand->name,
                ]
            ];
        }

        return [];
    }

    protected function prepareFlatAttributes(Products $product): array
    {
        if (!$product->productFlat) {
            return [];
        }

        return [
            'flat_attributes' => [
                'color' => $product->productFlat->color ?? null,
                'size' => $product->productFlat->size ?? null,
                'weight' => $product->productFlat->weight !== null ? (float)$product->productFlat->weight : null,
            ]
        ];
    }


    protected function prepareAttributes(Products $product): array
    {
        if (empty($product->productAttributeValues)) {
            return [];
        }

        $attributes = [];

        foreach ($product->productAttributeValues as $attributeValue) {
            $attribute = $attributeValue->attribute;

            if (!$attribute) {
//                Yii::warning("Атрибут с ID {$attributeValue->attribute_id} не найден для продукта {$product->id}");
                continue;
            }

            $attributeData = [
                'id' => $attribute->id,
                'name' => $attribute->name,
            ];

            if ($attributeValue->value_id !== null && $attributeValue->attributeOption) {
                $attributeData['value'] = [
                    'id' => $attributeValue->attributeOption->id,
                    'value' => $attributeValue->attributeOption->value,
                    'slug' => $attributeValue->attributeOption->slug ?? null,
                    'sort_order' => $attributeValue->attributeOption->sort_order ?? null,
                ];
            } else {
                $attributeData['value'] = $attributeValue->value ?? null;
            }

            $attributes[] = $attributeData;
        }

        return ['attributes' => $attributes];
    }


    protected function prepareAndCacheProductData(Products $product): array
    {
        $core = $this->cacheBlock("product:core:{$product->id}", fn() => $this->prepareBasic($product));
        $category = $this->cacheBlock("product:category:{$product->id}", fn() => $this->prepareCategory($product));
        $brand = $this->cacheBlock("product:brand:{$product->id}", fn() => $this->prepareBrand($product));
        $flat = $this->cacheBlock("product:flat:{$product->id}", fn() => $this->prepareFlatAttributes($product));
        $attributes = $this->cacheBlock("product:attributes:{$product->id}", fn() => $this->prepareAttributes($product));

        return array_merge($core, $category, $brand, $flat, $attributes);
    }


    protected function cacheBlock(string $tags, callable $builder, int $ttl = 3600): array
    {
        return Yii::$app->cache->getOrSet($tags, function () use ($builder) {
            return $builder();
        }, $ttl, new TagDependency(['tags' => [$tags]]));
    }


}

// Основной метод для пачек
//public function prepareBatch(array $products): array
//{
//    return array_map([$this, 'prepareProductData'], $products);
//}
//
//protected function prepareProductData(array $product): array
//{
//    return [
//        'id' => $product['id'],
//        'name' => $product['name'],
//        'category' => $this->prepareCategory($product),
//        'attributes' => $this->prepareAttributes($product['attributes'] ?? [])
//    ];
//}
//
//// Все методы ниже работают только с массивами!
//protected function prepareCategory(array $category): array
//{
//    return [
//        'id' => $category['id'] ?? null,
//        'name' => $category['name'] ?? ''
//    ];
//}
//
//protected function prepareAttributes(array $attributes): array
//{
//    return array_map(fn($attr) => [
//        'id' => $attr['id'],
//        'name' => $attr['name'],
//        'value' => $attr['value'] ?? null
//    ], $attributes);
//}
//}