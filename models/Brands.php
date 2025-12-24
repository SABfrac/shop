<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "brands".
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string|null $logo
 * @property int|null $status
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class Brands extends \yii\db\ActiveRecord
{

    const STATUS_ACTIVE = 1;
    const STATUS_DRAFT = 0;
    const STATUS_ARCHIVE = -1;



    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'brands';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['description', 'logo'], 'default', 'value' => null],
            [['status'], 'default', 'value' => 1],
            [['name'], 'required'],
            [['description'], 'string'],
            [['status'], 'default', 'value' => null],
            [['status'], 'integer'],
            [['created_at', 'updated_at'], 'safe'],
            [['name', 'logo'], 'string', 'max' => 255],
            [['name'], 'unique'],
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
            'description' => 'Description',
            'logo' => 'Logo',
            'status' => 'Status',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }


    public static function getStatuses(): array
    {
        return [
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_ARCHIVE => 'Archive'
        ];
    }


    public function getProducts()
    {
        return $this->hasMany(GlobalProducts::class, ['brand_id' => 'id']);
    }

    public function getCategories()
    {
        return $this->hasMany(Categories::class, ['id' => 'category_id'])
            ->viaTable('brand_category', ['brand_id' => 'id']);
    }

    public function getBrandCategories()
    {
        return $this->hasMany(BrandCategory::class, ['brand_id' => 'id']);
    }



}
