<?php

namespace app\models;


use yii\base\Model;

class ProductForm extends Model
{
    public $id;
    public $name;
    public $brand_Id;
    public $categoryId;
    public $attributes;

    public function rules()
    {
        return [
            [['name', 'brandId', 'categoryId'], 'required'],
            [['name'], 'string', 'max' => 255],
            [['id', 'brandId', 'categoryId'], 'integer'],

            // Проверяем, что attributes - это массив
            ['attributes', 'each', 'rule' => ['integer']],
        ];
    }


}