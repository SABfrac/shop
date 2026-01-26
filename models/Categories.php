<?php

namespace app\models;

use Yii;



/**
 * This is the model class for table "categories".
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $image
 * @property int|null $sort_order
 * @property int|null $status
 * @property bool|null $is_leaf
 * @property string $created_at
 * @property string $updated_at
 *
 * @property Categories[] $categories
 * @property Categories $parent
 */
class Categories extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'categories';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['parent_id', 'description', 'image'], 'default', 'value' => null],
            [['sort_order'], 'default', 'value' => 0],
            [['status'], 'default', 'value' => 1],
            [['parent_id', 'sort_order', 'status'], 'default', 'value' => null],
            [['parent_id', 'sort_order', 'status'], 'integer'],
            [['name', 'slug'], 'required'],
            [['description'], 'string'],
            [['created_at', 'updated_at'], 'safe'],
            [['name', 'slug', 'image'], 'string', 'max' => 255],
            [['slug'], 'unique'],
            [['parent_id'], 'exist', 'skipOnError' => true, 'targetClass' => Categories::class, 'targetAttribute' => ['parent_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'parent_id' => 'Parent ID',
            'name' => 'Name',
            'slug' => 'Slug',
            'description' => 'Description',
            'image' => 'Image',
            'sort_order' => 'Sort Order',
            'status' => 'Status',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

    /**
     * Gets query for [[Categories]].
     *
     * @return \yii\db\ActiveQuery
     */
    // Связь с дочерними категориями
    public function getChildren()
    {
        return $this->hasMany(Categories::class, ['parent_id' => 'id']);
    }

    /**
     * Gets query for [[Parent]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getParent()
    {
        return $this->hasOne(Categories::class, ['id' => 'parent_id']);
    }

    // Связь с продуктами
    public function getProducts()
    {
        return $this->hasMany(GlobalProducts::class, ['category_id' => 'id']);
    }

//    public function getAttributes()
//    {
//        return $this->hasMany(Attributes::class, ['id' => 'attribute_id'])
//            ->viaTable('category_attributes', ['category_id' => 'id']);
//    }


    public function getCategories()
    {
        return $this->hasMany(Categories::class, ['id' => 'category_id'])
            ->viaTable('category_attributes', ['attribute_id' => 'id']);
    }

    /**
     * получаем все бренды категории
     */
    public function getBrands()
    {
        return $this->hasMany(Brands::class, ['id' => 'brand_id'])
            ->viaTable('{{%brand_category}}', ['category_id' => 'id']);
    }


    public function getFeedAttributeSchema(): array
    {
        return Attributes::find()
            ->select('name')
            ->innerJoin('category_attributes ca', 'ca.attribute_id = id')
            ->where([
                'ca.category_id' => $this->id,
                'ca.is_variant' => true,
            ])
            ->column();
    }

}
