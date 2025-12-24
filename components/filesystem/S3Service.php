<?php

namespace app\components\filesystem;


use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;

class S3Service extends Component
{

    public string $key = '';
    public string $secret = '';
    public string $region = 'us-east-1'; // Например, ru-central1 для Яндекс
    public string $bucket = '';
    public string $endpoint = ''; // Нужно для MinIO или Яндекс.Облака
    public string $version = 'latest';

    private ?S3Client $client = null;

    public function init()
    {
        parent::init();
        if (empty($this->key) || empty($this->secret) || empty($this->bucket)) {
            throw new InvalidConfigException("S3Service: key, secret and bucket are required.");
        }
    }

    private function getClient(): S3Client
    {
        if ($this->client === null) {
            $config = [
                'credentials' => [
                    'key'    => $this->key,
                    'secret' => $this->secret,
                ],
                'region'  => $this->region,
                'version' => $this->version,
            ];

            // Если используется не AWS (например, MinIO или Яндекс), указываем endpoint
            if ($this->endpoint) {
                $config['endpoint'] = $this->endpoint;
                // Для MinIO часто нужно:
                $config['use_path_style_endpoint'] = true;
            }

            $this->client = new S3Client($config);
        }
        return $this->client;
    }

    /**
     * Загружает строку (содержимое файла) в S3 и возвращает публичный URL (или путь)
     */
    public function upload(string $filename, string $content, string $contentType = 'text/csv',?string $bucket = null): void
    {
        try {
            $bucket = $bucket ?? $this->bucket;

            $result = $this->getClient()->putObject([
                'Bucket' =>  $bucket,
                'Key'    => $filename,
                'Body'   => $content,
                'ContentType' => $contentType
            ]);

            Yii::info("File '$filename' uploaded to bucket '$bucket'", 's3');
        } catch (S3Exception $e) {
            Yii::error("S3 Upload Failed: " . $e->getMessage(), 's3');
            throw $e;
        }
    }

    /**
     * Генерация временной ссылки (если файлы приватные)
     */
    public function getPresignedUrl(string $filename, string $duration = '+1 hour', ?string $bucket = null): string
    {
        $cmd = $this->getClient()->getCommand('GetObject', [
            'Bucket' => $bucket,
            'Key'    => $filename
        ]);
        $request = $this->getClient()->createPresignedRequest($cmd, $duration);
        return (string)$request->getUri();
    }


    public function createBucket(string $bucketName): void
    {
        if (!$this->getClient()->doesBucketExist($bucketName)) {
            $this->getClient()->createBucket(['Bucket' => $bucketName]);
        }
    }

}