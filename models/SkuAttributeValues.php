<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "sku_attribute_values".
 *
 * @property int $id
 * @property int $sku_id
 * @property int $attribute_id
 * @property string|null $value_string
 * @property int|null $value_int
 * @property float|null $value_float
 * @property bool|null $value_bool
 * @property int|null $category_attribute_option_id
 * @property string|null $created_at
 * @property string|null $updated_at
 *
 * @property ProductSkus $sku
 */
class SkuAttributeValues extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'sku_attribute_values';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['value_string', 'value_int', 'value_float', 'value_bool', 'attribute_option_id'], 'default', 'value' => null],
            [['sku_id', 'attribute_id'], 'required'],
            [['sku_id', 'attribute_id', 'value_int', 'attribute_option_id'], 'default', 'value' => null],
            [['sku_id', 'attribute_id', 'value_int', 'attribute_option_id'], 'integer'],
            [['value_float'], 'number'],
            [['value_bool'], 'boolean'],
            [['created_at', 'updated_at'], 'safe'],
            [['value_string'], 'string', 'max' => 255],
            [['sku_id', 'attribute_id'], 'unique', 'targetAttribute' => ['sku_id', 'attribute_id']],
            [['sku_id'], 'exist', 'skipOnError' => true, 'targetClass' => ProductSkus::class, 'targetAttribute' => ['sku_id' => 'id']],
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

    /**
     * Gets query for [[Sku]].
     *
     * @return \yii\db\ActiveQuery
     */
//    public function getSku()
//    {
//        return $this->hasOne(ProductSkus::class, ['id' => 'sku_id']);
//    }

}
