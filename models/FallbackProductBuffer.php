<?php

namespace app\models;


use Yii;

/**
 * This is the model class for table "fallback_product_buffer".
 *
 * @property int $id
 * @property string $type Тип операции (insert/update)
 * @property string $payload Данные для обработки в формате JSON
 * @property int $created_at Временная метка создания записи
 */
class FallbackProductBuffer extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'fallback_product_buffer';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['type', 'payload', 'created_at'], 'required'],
            [['payload'], 'safe'],
            [['created_at'], 'default', 'value' => null],
            [['created_at'], 'integer'],
            [['type'], 'string', 'max' => 10],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'type' => 'Type',
            'payload' => 'Payload',
            'created_at' => 'Created At',
        ];
    }

}
