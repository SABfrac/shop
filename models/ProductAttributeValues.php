<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "product_attribute_values".
 *
 * @property int $id
 * @property int $global_product_id
 * @property int $attribute_id
 * @property string|null $value_string
 * @property int|null $value_int
 * @property float|null $value_float
 * @property bool|null $value_bool
 * @property int|null $category_attribute_options_id
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
            [['value_string', 'value_int', 'value_float', 'value_bool', 'category_attribute_options_id'], 'default', 'value' => null],
            [['global_product_id', 'attribute_id'], 'required'],
            [['global_product_id', 'attribute_id', 'value_int', 'category_attribute_options_id'], 'default', 'value' => null],
            [['global_product_id', 'attribute_id', 'value_int', 'category_attribute_options_id'], 'integer'],
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
            'global_product_id' => 'Global Product ID',
            'attribute_id' => 'Attribute ID',
            'value_string' => 'Value String',
            'value_int' => 'Value Int',
            'value_float' => 'Value Float',
            'value_bool' => 'Value Bool',
            'category_attribute_options_id' => 'Category Attribute Options ID',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }



    public function getProducts()
    {
        return $this->hasOne(GlobalProducts::class, ['id' => 'global_product_id']);
    }

// Связь с атрибутом
    public function getAttributeRelation()
    {
        return $this->hasOne(Attributes::class, ['id' => 'attribute_id']);
    }


// Связь с вариантом значения
    public function getAttributeOption()
    {
        return $this->hasOne(CategoryAttributeOption::class, ['id' => 'category_attribute_options_id']);
    }

}
