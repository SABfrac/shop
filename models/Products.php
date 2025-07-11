<?php

namespace app\models;
use yii\base\BaseObject;
use app\models\Brands;
use app\jobs\PrepareAndSyncProductJob;
use yii\caching\TagDependency;



use Yii;


/**
 * This is the model class for table "products".
 *
 * @property int $id
 * @property int $vendor_id
 * @property int $category_id
 * @property string $name
 * @property string|null $slug
 * @property string|null $description
 * @property float $price
 * @property int|null $status
 * @property int|null $quantity
 * @property string|null $created_at
 * @property string|null $updated_at
 * @property int|null $brand_id
 */
class Products extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'products';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['slug', 'description', 'brand_id'], 'default', 'value' => null],
            [['status'], 'default', 'value' => 10],
            [['quantity'], 'default', 'value' => 0],
            [['vendor_id', 'category_id', 'name', 'price'], 'required'],
            [['vendor_id', 'category_id', 'status', 'quantity', 'brand_id'], 'default', 'value' => null],
            [['vendor_id', 'category_id', 'status', 'quantity', 'brand_id'], 'integer'],
            [['description'], 'string'],
            [['price'], 'number'],
            [['created_at', 'updated_at'], 'safe'],
            [['name', 'slug'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'vendor_id' => 'Vendor ID',
            'category_id' => 'Category ID',
            'name' => 'Name',
            'slug' => 'Slug',
            'description' => 'Description',
            'price' => 'Price',
            'status' => 'Status',
            'quantity' => 'Quantity',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'brand_id' => 'Brand ID',
        ];
    }









    // Связь с вендором
    public function getVendor()
    {
        return $this->hasOne(Vendors::class, ['id' => 'vendor_id']);
    }

    // Связь с категорией
    public function getCategory()
    {
        return $this->hasOne(Categories::class, ['id' => 'category_id']);
    }

    // Связь с плоской таблицей
    public function getProductFlat()
    {
        return $this->hasOne(ProductFlat::class, ['product_id' => 'id']);
    }

    // Связь со значениями атрибутов
    public function getProductAttributeValues()
    {
        return $this->hasMany(ProductAttributeValues::class, ['product_id' => 'id']);
    }

//     Связь с атрибутами через значения
    public function getProductAttributes()
    {
        return $this->hasMany(Attributes::class, ['id' => 'attribute_id'])
            ->via('productAttributeValues') ;
    }

    public function getBrand()
    {
        return $this->hasOne(Brands::class, ['id' => 'brand_id']);
    }

}
