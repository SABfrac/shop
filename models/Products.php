<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "products".
 *
 * @property int $id
 * @property string $name
 * @property int $category_id
 * @property string|null $description
 * @property int|null $brand_id
 * @property string|null $slug
 * @property int|null $status
 * @property string|null $created_at
 * @property string|null $updated_at
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
            [['description', 'brand_id', 'slug'], 'default', 'value' => null],
            [['status'], 'default', 'value' => 10],
            [['name', 'category_id'], 'required'],
            [['category_id', 'brand_id', 'status'], 'default', 'value' => null],
            [['category_id', 'brand_id', 'status'], 'integer'],
            [['description'], 'string'],
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
            'name' => 'Name',
            'category_id' => 'Category ID',
            'description' => 'Description',
            'brand_id' => 'Brand ID',
            'slug' => 'Slug',
            'status' => 'Status',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }


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
