<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "attribute_options".
 *
 * @property int $id
 * @property int $attribute_id
 * @property string $value
 * @property string|null $slug
 * @property int|null $sort_order
 * @property string|null $created_at
 * @property string|null $updated_at
 *
 * @property Attributes $attribute0
 */
class AttributeOptions extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'attribute_options';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['slug'], 'default', 'value' => null],
            [['sort_order'], 'default', 'value' => 0],
            [['attribute_id', 'value'], 'required'],
            [['attribute_id', 'sort_order'], 'default', 'value' => null],
            [['attribute_id', 'sort_order'], 'integer'],
            [['created_at', 'updated_at'], 'safe'],
            [['value', 'slug'], 'string', 'max' => 255],
            [['slug'], 'unique'],
            [['attribute_id'], 'exist', 'skipOnError' => true, 'targetClass' => Attributes::class, 'targetAttribute' => ['attribute_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'attribute_id' => 'Attribute ID',
            'value' => 'Value',
            'slug' => 'Slug',
            'sort_order' => 'Sort Order',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

    /**
     * Gets query for [[Attribute0]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getAttribute()
    {
        return $this->hasOne(Attributes::class, ['id' => 'attribute_id']);
    }


    public function getProducts()
    {
        return $this->hasMany(Products::class, ['id' => 'product_id'])
            ->via('product_attribute_values');
    }

    public function getProductAttributeValues()
    {
        return $this->hasMany(ProductAttributeValues::class, ['value_id' => 'id']);
    }


}
