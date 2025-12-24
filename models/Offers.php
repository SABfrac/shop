<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "offers".
 *
 * @property int $id ID предложения
 * @property string $vendor_sku код предложения комбинации товара
 * @property int $sku_id ID товара (модели)
 * @property int $vendor_id ID продавца (из таблицы vendor)
 * @property float $price Цена
 * @property int $stock Количество на складе
 * @property int|null $warranty
 * @property string $condition Состояние (new, used, refurbished)
 * @property bool $status Активно ли предложение (прошло модерацию)
 * @property int $sort_order Порядок сортировки
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class Offers extends \yii\db\ActiveRecord
{


    const STATUS_INACTIVE = 0;
    const STATUS_ACTIVE = 1;
    const STATUS_MODERATION = 2;
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
            [['sort_order'], 'default', 'value' => 0],
            [['condition'], 'default', 'value' => 'new'],
            [['sku_id', 'vendor_id', 'price'], 'required'],
            [['sku_id', 'vendor_id', 'stock', 'warranty', 'sort_order'], 'default', 'value' => null],
            [['sku_id', 'vendor_id', 'stock', 'warranty', 'sort_order','status'], 'integer'],
            [['price'], 'number'],
            [['created_at', 'updated_at'], 'safe'],
            [['condition'], 'string', 'max' => 50],
            [['vendor_sku'],'unique'],
            [['vendor_sku'],'string'],
            [['vendor_id', 'sku_id',], 'unique', 'targetAttribute' => ['vendor_id', 'sku_id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'sku_id' => 'Sku ID',
            'vendor_id' => 'Vendor ID',
            'vendor_sku'=>'Vendor Sku',
            'price' => 'Price',
            'stock' => 'Stock',
            'warranty' => 'Warranty',
            'condition' => 'Condition',
            'status' => 'Status',
            'sort_order' => 'Sort Order',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }
//    public function getProductSkus()
//    {
//        return $this->hasOne(ProductSkus::class, ['id' => 'sku_id']);
//    }

    public function getVendor()
    {
        return $this->hasOne(Vendors::class, ['id' => 'vendor_id']);
    }


    public function getSku()
    {
        return $this->hasOne(ProductSkus::class, ['id' => 'sku_id']);
    }



}
