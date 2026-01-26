<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';
$diConfig = require __DIR__ . '/di.php';

$config = [
    'id' => 'basic-console',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log','queue'],
    'controllerNamespace' => 'app\commands',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
        '@tests' => '@app/tests',
    ],
    'components' => [
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'redis' => [
            'class' => 'yii\redis\Connection',
            'hostname' => getenv('REDIS_HOST') ?: 'redis',  // или IP сервера Redis ,если разворачиваем через docker то имя ставим redis
            'port' => getenv('REDIS_PORT') ?: 6379,
            'database' => 0,
            'retries' => 3

        ],

        'queue' => [
            'class' => 'yii\queue\redis\Queue',
            'redis' => 'redis',         // Компонент подключения к Redis
            'channel' => 'queue', // Название канала
            'as log' => \yii\queue\LogBehavior::class,
            'ttr' => 5 * 60, // Максимальное время выполнения задания
            'attempts' => 3, // Максимальное количество попыток

        ],
        'opensearch' => [
            'class' => 'app\components\OpenSearch',
            'hosts' => ['http://opensearch:9200'],
            'index' => 'products',
        ],

        'rabbitmq' => [
            'class' => 'app\components\RabbitMQ',
            'host' => getenv('RABBITMQ_HOST') ?: 'rabbitmq', // ← имя сервиса в docker-compose
            'port' => (int)(getenv('RABBITMQ_PORT') ?: 5672),
            'user' => getenv('RABBITMQ_USER') ?: 'guest',
            'password' => getenv('RABBITMQ_PASSWORD') ?: 'guest',
            'vhost' => '/',
        ],

        's3Reports' => [
            'class' => 'app\components\filesystem\S3Service',
            'key' => 'minioadmin',
            'secret' => 'minioadmin',
            'bucket' => 'feed-reports', // создайте его вручную в UI или через код
            'endpoint' => 'http://minio:9000',
            'region' => 'us-east-1', // MinIO игнорирует регион, но SDK требует его
            'version' => 'latest',
        ],

        'imageManager' => [
            'class' => 'app\components\image\product\ImageManager',
        ],

        's3Images' => [
            'class' => 'app\components\filesystem\S3Service',
            'key' => 'minioadmin',
            'secret' => 'minioadmin',
            'bucket' => 'marketplace-images',
            'endpoint' => 'http://minio:9000', // ← имя сервиса в Docker!
            'region' => 'us-east-1',
            'version' => 'latest',

        ],
        'log' => [
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning', 'info'],
                    'logFile' => '@runtime/logs/console.log',
                ],
            ],
        ],
        'db' => $db,
    ],
    'params' => $params,
    /*
    'controllerMap' => [
        'fixture' => [ // Fixture generation command line.
            'class' => 'yii\faker\FixtureController',
        ],
    ],
    */
];
$config = yii\helpers\ArrayHelper::merge($config, $diConfig);

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
    ];
    // configuration adjustments for 'dev' environment
    // requires version `2.1.21` of yii2-debug module
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
