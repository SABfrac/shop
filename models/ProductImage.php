<?php

namespace app\models;

use app\traits\VendorAuthTrait;
use Yii;
use yii\db\ActiveRecord;


/**
 * @property int $id
 * @property string $entity_type
 * @property int $entity_id
 * @property string $storage_path
 * @property string|null $filename
 * @property bool $is_main
 * @property int $sort_order
 * @property string $created_at
 */

class ProductImage extends ActiveRecord
{

    use VendorAuthTrait;
    const ENTITY_GLOBAL_PRODUCT = 'global_product';
    const ENTITY_OFFER = 'offer';

    public static function tableName()
    {
        return '{{%product_images}}';
    }

    public function rules()
    {
        return [
            [['entity_type', 'entity_id', 'storage_path'], 'required'],
            [['entity_id', 'sort_order'], 'integer'],
            [['is_main'], 'boolean'],
            [['created_at'], 'safe'],
            [['entity_type'], 'string', 'max' => 50],
            [['storage_path', 'filename'], 'string', 'max' => 255],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'entity_type' => 'Entity Type',
            'entity_id' => 'Entity ID',
            'storage_path' => 'Storage Path',
            'filename' => 'Filename',
            'is_main' => 'Is Main',
            'sort_order' => 'Sort Order',
            'created_at' => 'Created At',
        ];
    }


    public function getOffer()
    {
        return $this->hasOne(Offers::class, ['id' => 'entity_id'])
            ->andOnCondition(['{{%product_images}}.entity_type' => self::ENTITY_OFFER]);
    }

    /**
     * Связь с GlobalProduct (если entity_type = 'global_product')
     *
     * @return \yii\db\ActiveQuery
     */
    public function getGlobalProduct()
    {
        return $this->hasOne(GlobalProducts::class, ['id' => 'entity_id'])
            ->andOnCondition(['{{%product_images}}.entity_type' => self::ENTITY_GLOBAL_PRODUCT]);
    }







}