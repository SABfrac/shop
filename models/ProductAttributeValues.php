<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "product_attribute_values".
 *
 * @property int $id
 * @property int $product_id
 * @property int $attribute_id
 * @property string|null $value_string
 * @property int|null $value_int
 * @property float|null $value_float
 * @property bool|null $value_bool
 * @property int|null $attribute_option_id
 * @property string|null $created_at
 * @property string|null $updated_at
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
            [['value_string', 'value_int', 'value_float', 'value_bool', 'attribute_option_id'], 'default', 'value' => null],
            [['product_id', 'attribute_id'], 'required'],
            [['product_id', 'attribute_id', 'value_int', 'attribute_option_id'], 'default', 'value' => null],
            [['product_id', 'attribute_id', 'value_int', 'attribute_option_id'], 'integer'],
            [['value_float'], 'number'],
            [['value_bool'], 'boolean'],
            [['created_at', 'updated_at'], 'safe'],
            [['value_string'], 'string', 'max' => 255],
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
            'value_string' => 'Value String',
            'value_int' => 'Value Int',
            'value_float' => 'Value Float',
            'value_bool' => 'Value Bool',
            'attribute_option_id' => 'Attribute Option ID',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }



    public function getProducts()
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
        return $this->hasOne(AttributeOptions::class, ['id' => 'attribute_option_id']);
    }

}
