<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "user".
 *
 * @property int $id
 * @property string $username
 * @property string $email
 * @property string $password_hash
 * @property string|null $auth_key
 * @property int $status
 * @property string $role
 * @property string|null $vendor_name
 * @property string|null $vendor_description
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class User extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'user';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['auth_key', 'vendor_name', 'vendor_description'], 'default', 'value' => null],
            [['status'], 'default', 'value' => 10],
            [['role'], 'default', 'value' => 'customer'],
            [['username', 'email', 'password_hash'], 'required'],
            [['status'], 'default', 'value' => null],
            [['status'], 'integer'],
            [['vendor_description'], 'string'],
            [['created_at', 'updated_at'], 'safe'],
            [['username', 'email', 'password_hash', 'vendor_name'], 'string', 'max' => 255],
            [['auth_key'], 'string', 'max' => 32],
            [['role'], 'string', 'max' => 64],
            [['email'], 'unique'],
            [['username'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'username' => 'Username',
            'email' => 'Email',
            'password_hash' => 'Password Hash',
            'auth_key' => 'Auth Key',
            'status' => 'Status',
            'role' => 'Role',
            'vendor_name' => 'Vendor Name',
            'vendor_description' => 'Vendor Description',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

}
