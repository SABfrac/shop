<?php

namespace app\traits;

use Yii;
use yii\web\UnauthorizedHttpException;




trait VendorAuthTrait
{
    private $_vendorId = null;



    /**
     * Проверяет JWT из cookie и возвращает vendor_id из токена
     * @return int|null
     * @throws \yii\web\UnauthorizedHttpException
     */

    protected function getAuthorizedVendorId(): int
    {
        if ($this->_vendorId !== null) {
            return $this->_vendorId;
        }

        $token = $this->getAuthTokenFromCookie();
        if (!$token) {
            throw new UnauthorizedHttpException('Не авторизован');
        }

        try {
            $payload = Yii::$app->jwt->decodeToken($token);
            $vendorId = $payload['vendor_id'] ?? null;
            $jti = $payload['jti'] ?? null;

            if (!$vendorId) {
                throw new UnauthorizedHttpException('Некорректный токен');
            }

            if ($jti && Yii::$app->redis->exists("blacklist:jti:{$jti}")) {
                throw new UnauthorizedHttpException('Сессия завершена');
            }

            return $this->_vendorId = (int)$vendorId;
        } catch (\Exception $e) {
            throw new UnauthorizedHttpException('Неверный или просроченный токен');
        }
    }

    /**
     * Получает JWT из HttpOnly cookie
     * @return string|null
     */
   protected function getAuthTokenFromCookie()
    {
        return Yii::$app->request->cookies->getValue('auth_token');
    }

}