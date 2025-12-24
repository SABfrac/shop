<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "product_skus".
 *
 * @property int $id
 * @property int $global_product_id
 * @property string $variant_hash
 * @property string $variant_values
 * @property string|null $barcode GTIN/EAN/UPC (если есть)
 * @property int $status
 * @property string|null $created_at
 * @property string|null $updated_at
 *
 * @property GlobalProducts $product
 * @property SkuAttributeValues[] $skuAttributeValues
 */
class ProductSkus extends \yii\db\ActiveRecord
{
    const STATUS_INACTIVE = 0;
    const STATUS_ACTIVE = 1;
    const STATUS_MODERATION = 2;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'product_skus';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [[ 'barcode'], 'default', 'value' => null],
            [['status'], 'default', 'value' => 1],
            [['global_product_id', 'variant_hash'], 'required'],
            [['global_product_id', 'status'], 'default', 'value' => null],
            [['global_product_id', 'status'], 'integer'],
            [['variant_values', 'created_at', 'updated_at'], 'safe'],
            [['variant_hash'], 'string', 'max' => 64],
            [['barcode'], 'string', 'max' => 32],
            [['global_product_id', 'variant_hash'], 'unique', 'targetAttribute' => ['global_product_id', 'variant_hash']],
            [['global_product_id'], 'exist', 'skipOnError' => true, 'targetClass' => GlobalProducts::class, 'targetAttribute' => ['global_product_id' => 'id']],
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
            'variant_hash' => 'Variant Hash',
            'variant_values' => 'Variant Values',
            'barcode' => 'Barcode',
            'status' => 'Status',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

    /**
     * Gets query for [[Product]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getGlobalProduct()
    {
        return $this->hasOne(GlobalProducts::class, ['id' => 'global_product_id']);
    }

    /**
     * Gets query for [[SkuAttributeValues]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getSkuAttributeValues()
    {
        return $this->hasMany(SkuAttributeValues::class, ['sku_id' => 'id']);
    }


    public function getOffers()
    {
        return $this->hasMany(Offers::class, ['sku_id' => 'id']);
    }

}
