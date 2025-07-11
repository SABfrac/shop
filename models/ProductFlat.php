<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "product_flat".
 *
 * @property int $id
 * @property int $product_id
 * @property string $name
 * @property float $price
 * @property int|null $brand_id
 * @property string|null $brand
 * @property string|null $color
 * @property string|null $size
 * @property float|null $weight
 * @property string|null $search_vector
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
            [['brand_id', 'brand', 'color', 'size', 'weight', 'search_vector'], 'default', 'value' => null],
            [['product_id', 'name', 'price'], 'required'],
            [['product_id', 'brand_id'], 'default', 'value' => null],
            [['product_id', 'brand_id'], 'integer'],
            [['price', 'weight'], 'number'],
            [['name', 'brand', 'color', 'size'], 'string', 'max' => 255],
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
            'name' => 'Name',
            'price' => 'Price',
            'brand_id' => 'Brand ID',
            'brand' => 'Brand',
            'color' => 'Color',
            'size' => 'Size',
            'weight' => 'Weight',
            'search_vector' => 'Search Vector',
        ];
    }


    // Связь с продуктом
    public function getProduct()
    {
        return $this->hasOne(Product::class, ['id' => 'product_id']);
    }

}
