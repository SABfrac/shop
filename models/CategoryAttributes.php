<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "category_attributes".
 *
 * @property int $category_id
 * @property int $attribute_id
 * @property bool $is_variant
 * @property int|null $sort_order
 *
 * @property Attributes $attribute0
 * @property Categories $category
 */
class CategoryAttributes extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'category_attributes';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['sort_order'], 'default', 'value' => 0],
            [['category_id', 'attribute_id'], 'required'],
            [['category_id', 'attribute_id', 'sort_order'], 'default', 'value' => null],
            [['category_id', 'attribute_id', 'sort_order'], 'integer'],
            [['category_id', 'attribute_id'], 'unique', 'targetAttribute' => ['category_id', 'attribute_id']],
            [['attribute_id'], 'exist', 'skipOnError' => true, 'targetClass' => Attributes::class, 'targetAttribute' => ['attribute_id' => 'id']],
            [['category_id'], 'exist', 'skipOnError' => true, 'targetClass' => Categories::class, 'targetAttribute' => ['category_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'category_id' => 'Category ID',
            'attribute_id' => 'Attribute ID',
            'is_variant'=>'Is Variant',
            'sort_order' => 'Sort Order',
        ];
    }

    /**
     * Gets query for [[Attribute0]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getAttribute0()
    {
        return $this->hasOne(Attributes::class, ['id' => 'attribute_id']);
    }

    /**
     * Gets query for [[Category]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getCategory()
    {
        return $this->hasOne(Categories::class, ['id' => 'category_id']);
    }

}
