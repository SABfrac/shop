<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "product_attribute_values".
 *
 * @property int $id
 * @property int $product_id
 * @property int $attribute_id
 * @property int|null $value_id
 */
class ProductAttributeValues extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'product_attribute_values';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['value_id'], 'default', 'value' => null],
            [['product_id', 'attribute_id'], 'required'],
            [['product_id', 'attribute_id', 'value_id'], 'default', 'value' => null],
            [['product_id', 'attribute_id', 'value_id'], 'integer'],
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
            'attribute_id' => 'Attribute ID',
            'value_id' => 'Value ID',
        ];
    }

    public function getProduct()
    {
        return $this->hasOne(Products::class, ['id' => 'product_id']);
    }

// Связь с атрибутом
    public function getAttribute()
    {
        return $this->hasOne(Attributes::class, ['id' => 'attribute_id']);
    }


// Связь с вариантом значения
    public function getAttributeOption()
    {
        return $this->hasOne(AttributeOptions::class, ['id' => 'value_id']);
    }


}
