<?php

namespace app\components\image\product;

use Yii;
use yii\base\Component;

class ImageManager
{

    /**
     * Генерация ссылки на картинку
     *
     * @param string $storagePath Путь в бакете (напр. "vendors/5/100.jpg")
     * @param int|null $width Ширина
     * @param int|null $height Высота
     * @param string $operation Тип операции: fit, resize, crop
     * @return string
     */

    public function getUrl($storagePath, $width = null, $height = null, $operation = 'fit')
    {



        $presignedUrl = Yii::$app->s3Images->getPresignedUrl(
            $storagePath,
            '+1 hour',
            'marketplace-images',
            'GET',
            'http://minio:9000' // ← внутренний endpoint для генерации подписи
        );



        // Базовый URL вашего сайта (Nginx)

        $publicBaseUrl =  '/images';

        $params = [
            'url' =>   $presignedUrl, // Imaginary скачает отсюда
            'gravity' => 'smart',
        ];
        if ($width) $params['width'] = $width;
        if ($height) $params['height'] = $height;

        // Итоговая ссылка для браузера:
        // http://localhost:8080/images/fit?url=http://minio:9000/bucket/path.jpg&width=300
        return $publicBaseUrl . '/' . $operation . '?' . http_build_query($params);
    }


    public function getPublicUrl(string $storagePath, ?int $width = null, ?int $height = null, string $operation = 'fit'): string
    {
        // Внутренний URL MinIO, доступный Imaginary (в Docker-сети)
        $minioInternalUrl = 'http://minio:9000/marketplace-images/' . ltrim($storagePath, '/');

        $presignedUrl = Yii::$app->s3Images->getPresignedUrl( // это избыточное решение и не подходит для highload маркетплейса генерация подписи при каждом запросе поиска,Не кэшируется надолго (подпись живёт 24ч),
            $storagePath,
            '+24 hours',
            'marketplace-images',
            'GET',
            'http://minio:9000' // внутренний endpoint
        );

        $params = [
            'url' =>  $presignedUrl,
            'gravity' => 'smart',
        ];

        if ($width !== null) {
            $params['width'] = $width;
        }
        if ($height !== null) {
            $params['height'] = $height;
        }

        // Возвращаем относительный путь — фронтенд сам добавит origin
        return '/images/' . $operation . '?' . http_build_query($params);
    }

}