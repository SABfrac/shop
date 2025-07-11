<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => 'wIfgF_wM2saVTt4r2IWoauf2GMz_LUGs',
        ],
        'queue' => [
            'class' =>' \yii\queue\redis\Queue',
            'redis' => 'redis',         // Компонент подключения к Redis
            'channel' => 'queue', // Название канала
            'as log' => \yii\queue\LogBehavior::class,
            'ttr' => 5 * 60, // Максимальное время выполнения задания
            'attempts' => 3, // Максимальное количество попыток

        ],


        'opensearch' => [
            'class' => 'app\components\OpenSearch',
            'hosts' => ['http://localhost:9200'],
            'index' => 'products',
        ],
        'rabbitmq' => [
            'class' => 'app\components\RabbitMQ',
            'host' => 'localhost',
            'port' => 5672,
            'user' => 'guest',
            'password' => 'guest',
            'vhost' => '/',
        ],

        'searchSynchronizer' => [
            'class' => 'app\components\SearchSynchronizer',
        ],
        'productQueue' => [
            'class' => 'app\components\ProductQueueComponent',
        ],

        'redis' => [
            'class' => 'yii\redis\Connection',
            'hostname' => 'localhost',  // или IP сервера Redis
            'port' => 6379,
            'database' => 0,
            'connectionTimeout' => 2, // Таймаут подключения (сек)
            'retries' => 3,

        ],

        'cache' => [
//            'class' => 'yii\caching\FileCache',
            'class' => 'yii\redis\Cache',
        ],
        'user' => [
            'identityClass' => 'app\models\User',
            'enableAutoLogin' => true,
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'viewPath' => '@app/mail',
            // send all mails to a file by default.
            'useFileTransport' => true,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,

        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
            ],
        ],

    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
