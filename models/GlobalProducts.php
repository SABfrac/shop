<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "products".
 *
 * @property int $id
 * @property string $canonical_name
 * @property int $category_id
 * @property string|null $description
 * @property string|null $model_number
 * @property string|null $gtin
 * @property string|null $match_key
 * @property int|null $brand_id
 * @property string|null $slug
 * @property int|null $status
 * @property array|null $attributes_json
 * @property string|null $canonical_name_normalized
 * @property string|null $created_at
 * @property string|null $updated_at
 *
 */
class GlobalProducts extends \yii\db\ActiveRecord
{
    const STATUS_ACTIVE = 10;


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'global_products';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['canonical_name', 'category_id'], 'required'],
            [['category_id', 'brand_id', 'status'], 'integer'],
            [['description'], 'string'],
            [['canonical_name', 'model_number', 'slug', 'gtin','canonical_name_normalized'], 'string', 'max' => 255],
            [['match_key'], 'string', 'max' => 512],
            [['gtin'], 'unique'],
            [['match_key'], 'unique'],
            [['model_number'], 'default', 'value' => null],
            [['brand_id'], 'default', 'value' => null],
            [['description'], 'default', 'value' => null],
            [['gtin'], 'default', 'value' => null],
            [['slug'], 'default', 'value' => null],
            [['attributes_json'], 'default', 'value' => []],
            [['status'], 'default', 'value' => 10],
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
            'model_number' => 'Model Number',
            'category_id' => 'Category ID',
            'description' => 'Description',
            'brand_id' => 'Brand ID',
            'slug' => 'Slug',
            'gtin' => 'GTIN',
            'status' => 'Status',
            'match_key' => 'Match Key',
            'attributes_json' => 'Attributes JSON',
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
        return $this->hasOne(ProductFlat::class, ['global_product_id' => 'id']);
    }

    // Связь со значениями атрибутов
    public function getProductAttributeValues()
    {
        return $this->hasMany(ProductAttributeValues::class, ['global_product_id' => 'id']);
    }

//     Связь с атрибутами через значения
//    public function getProductAttributes()
//    {
//        return $this->hasMany(Attributes::class, ['id' => 'attribute_id'])
//            ->via('productAttributeValues') ;
//    }

    public function getBrand()
    {
        return $this->hasOne(Brands::class, ['id' => 'brand_id']);
    }



}
