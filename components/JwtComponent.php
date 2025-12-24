<?php

namespace app\components;

use yii\base\Component;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtComponent extends Component
{

    public $key;

    /**
     * Генерирует JWT токен
     *
     * @param array $payload Полезная нагрузка
     * @param int|null $ttl Время жизни в секундах (по умолчанию — 24 часа)
     * @return string
     */
    private const DEFAULT_TTL = 3600 * 24; // 24 часа

    public function generateToken(array $payload, ?int $ttl = null): string
    {
        $now = time();
        $payload += [
            'iat' => $now,
            'exp' => $now + ($ttl ?? self::DEFAULT_TTL),
            'jti' => bin2hex(random_bytes(16)),
        ];

        return JWT::encode($payload, $this->key, 'HS256');
    }


    public function decodeToken($token)
    {
        try {
            return (array) JWT::decode($token, new Key($this->key, 'HS256'));
        } catch (\Firebase\JWT\ExpiredException $e) {
            throw new \yii\web\UnauthorizedHttpException('Токен просрочен');
        } catch (\Exception $e) {
            throw new \yii\web\UnauthorizedHttpException('Неверный токен');
        }
    }

}