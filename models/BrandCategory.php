<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "brand_category".
 *
 * @property int $brand_id
 * @property int $category_id
 */
class BrandCategory extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'brand_category';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['brand_id', 'category_id'], 'required'],
            [['brand_id', 'category_id'], 'default', 'value' => null],
            [['brand_id', 'category_id'], 'integer'],
            [['brand_id', 'category_id'], 'unique', 'targetAttribute' => ['brand_id', 'category_id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'brand_id' => 'Brand ID',
            'category_id' => 'Category ID',
        ];
    }

}
