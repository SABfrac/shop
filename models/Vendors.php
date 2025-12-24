<?php

namespace app\models;


use yii\db\ActiveRecord;
use yii\web\IdentityInterface;
use Yii;


/**
 * This is the model class for table "vendors".
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $passport
 * @property string $password_hash
 * @property string|null $email_confirm_token
 * @property int $status
 * @property string $balance
 * @property string $created_at
 * @property string $updated_at
 */
class Vendors extends ActiveRecord implements IdentityInterface
{
    public $password;
    const STATUS_INACTIVE = 0;
    const STATUS_ACTIVE = 1;

    public static function tableName()
    {
        return '{{%vendors}}';
    }

    public function rules()
    {
        return [
            [['name', 'email', 'passport', 'password'], 'required'],
            ['email', 'email'],
            ['email', 'unique'],
            ['passport', 'unique'],
            [['name', 'email', 'passport', 'password', 'email_confirm_token'], 'string', 'max' => 255],
            ['status', 'default', 'value' => self::STATUS_INACTIVE],
            ['status', 'in', 'range' => [self::STATUS_ACTIVE, self::STATUS_INACTIVE]],
        ];
    }

    // IdentityInterface methods
    public static function findIdentity($id)
    {
        return static::findOne(['id' => $id, 'status' => self::STATUS_ACTIVE]);
    }

    public static function findIdentityByAccessToken($token, $type = null)
    {
        try {
            // Используем ваш компонент
            $payload = Yii::$app->jwt->decodeToken($token);

            // Убедитесь, что в payload есть 'id' (или другое поле с ID пользователя)
            if (!isset($payload['id'])) {
                return null;
            }

            return static::findOne(['id' => $payload['id'], 'status' => self::STATUS_ACTIVE]);
        } catch (\Exception $e) {
            // Токен недействителен, просрочен, подделан и т.д.
            Yii::info('JWT decode error: ' . $e->getMessage(), __METHOD__);
            return null;
        }
    }

    public static function findByEmail($email)
    {
        return static::findOne(['email' => $email]);
    }

    public function getId()
    {
        return $this->getPrimaryKey();
    }

    public function getAuthKey()
    {
        return null;
    }

    public function validateAuthKey($authKey)
    {
        return true;
    }

    public function setPassword($password)
    {
        $this->password_hash = Yii::$app->security->generatePasswordHash($password);
    }

    public function validatePassword($password)
    {
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }



    public function generateEmailConfirmToken()
    {
        $this->email_confirm_token = Yii::$app->security->generateRandomString();
    }

    public function removeEmailConfirmToken()
    {
        $this->email_confirm_token = null;
    }


    public function getOffers()
    {
        return $this->hasMany(Offers::class, ['vendor_id' => 'id']);
    }

}