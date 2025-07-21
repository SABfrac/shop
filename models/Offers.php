<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "offers".
 *
 * @property int $id ID предложения
 * @property int $product_id ID товара (модели)
 * @property int $vendor_id ID продавца (из таблицы vendor)
 * @property float $price Цена
 * @property int $stock Количество на складе
 * @property string|null $sku Артикул продавца (SKU)
 * @property string $condition Состояние (new, used, refurbished)
 * @property bool $status Активно ли предложение (прошло модерацию)
 * @property int $sort_order Порядок сортировки
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class Offers extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'offers';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['sku'], 'default', 'value' => null],
            [['sort_order'], 'default', 'value' => 0],
            [['condition'], 'default', 'value' => 'new'],
            [['product_id', 'vendor_id', 'price'], 'required'],
            [['product_id', 'vendor_id', 'stock', 'sort_order'], 'default', 'value' => null],
            [['product_id', 'vendor_id', 'stock', 'sort_order'], 'integer'],
            [['price'], 'number'],
            [['status'], 'boolean'],
            [['created_at', 'updated_at'], 'safe'],
            [['sku'], 'string', 'max' => 255],
            [['condition'], 'string', 'max' => 50],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'product_id' => 'Product ID',
            'vendor_id' => 'Vendor ID',
            'price' => 'Price',
            'stock' => 'Stock',
            'sku' => 'Sku',
            'condition' => 'Condition',
            'status' => 'Status',
            'sort_order' => 'Sort Order',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

    public function getProduct()
    {
        return $this->hasOne(Products::class, ['id' => 'product_id']);
    }


    public function getVendor()
    {
        return $this->hasOne(Vendors::class, ['id' => 'vendor_id']);
    }

}
