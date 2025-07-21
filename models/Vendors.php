<?php

namespace app\models;



use Yii;

/**
 * This is the model class for table "vendors".
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property int|null $status
 * @property string|null $created_at
 */
class Vendors extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'vendors';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['status'], 'default', 'value' => 10],
            [['name', 'email'], 'required'],
            [['status'], 'default', 'value' => null],
            [['status'], 'integer'],
            [['created_at'], 'safe'],
            [['name', 'email'], 'string', 'max' => 255],
            [['email'], 'unique'],
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
            'email' => 'Email',
            'status' => 'Status',
            'created_at' => 'Created At',
        ];
    }



    // Связь с продуктами
    public function getProducts()
    {
        return $this->hasMany(Products::class, ['vendor_id' => 'id']);
    }








}
