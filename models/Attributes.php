<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "attributes".
 *
 * @property int $id
 * @property string $name
 * @property string $type
 * @property bool|null $is_filterable
 * @property bool|null $is_required
 */
class Attributes extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'attributes';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [

            [['name', 'type'], 'required'],
            [['is_filterable', 'is_required'], 'boolean'],
            [['name', 'type'], 'string', 'max' => 255],
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
            'type' => 'Type',
            'is_filterable' => 'Is Filterable',
            'is_required' => 'Is Required',
        ];
    }

    // Связь со значениями атрибутов
    public function getProductAttributeValues()
    {
        return $this->hasMany(ProductAttributeValues::class, ['attribute_id' => 'id']);
    }

    // Связь с продуктами через значения
    public function getProducts()
    {
        return $this->hasMany(Products::class, ['id' => 'product_id'])
            ->via('product_attribute_values');//через все значения атрибута мы получаем доступ ко всем продуктам, которые используют этот атрибут (Атрибут "Цвет" может принадлежать многим продуктам (например, "Красный" цвет может быть у телефона, футболки, автомобиля)
    }


    // Связь с вариантами значений
    public function getAttributeOptions()
    {
        return $this->hasMany(AttributeOptions::class, ['attribute_id' => 'id'])
            ->orderBy('sort_order');
    }


}
