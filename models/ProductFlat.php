<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "product_flat".
 *
 * @property int $id
 * @property int $product_id
 * @property int $category_id
 * @property string $name
 * @property float $price
 * @property int|null $brand_id
 * @property int|null $vendor_id
 * @property int|null $quantity
 * @property int|null $status
 * @property string|null $color
 * @property string|null $size
 * @property float|null $weight
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class ProductFlat extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'product_flat';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['brand_id', 'vendor_id', 'quantity', 'status', 'color', 'size', 'weight'], 'default', 'value' => null],
            [['product_id', 'category_id', 'name', 'price'], 'required'],
            [['product_id', 'category_id', 'brand_id', 'vendor_id', 'quantity', 'status'], 'default', 'value' => null],
            [['product_id', 'category_id', 'brand_id', 'vendor_id', 'quantity', 'status'], 'integer'],
            [['price', 'weight'], 'number'],
            [['created_at', 'updated_at'], 'safe'],
            [['name'], 'string', 'max' => 255],
            [['color', 'size'], 'string', 'max' => 50],
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
            'category_id' => 'Category ID',
            'name' => 'Name',
            'price' => 'Price',
            'brand_id' => 'Brand ID',
            'vendor_id' => 'Vendor ID',
            'quantity' => 'Quantity',
            'status' => 'Status',
            'color' => 'Color',
            'size' => 'Size',
            'weight' => 'Weight',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }



//    public function getProduct()
//    {
//        return $this->hasOne(GlobalProducts::class, ['id' => 'product_id']);
//    }

    public function getCategory()
    {
        return $this->hasOne(Categories::class, ['id' => 'category_id']);
    }

    public function getBrand()
    {
        return $this->hasOne(Brands::class, ['id' => 'brand_id']);
    }

    public function getVendor()
    {
        return $this->hasOne(Vendors::class, ['id' => 'vendor_id']);
    }
}
