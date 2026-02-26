<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';
$diConfig = require __DIR__ . '/di.php';





    $config = [
        'id' => 'basic',
        'basePath' => dirname(__DIR__),
        'bootstrap' => ['log'],
        'aliases' => [
            '@bower' => '@vendor/bower-asset',
            '@npm' => '@vendor/npm-asset',
        ],
        'modules' => [
            'api' => [
                'class' => \yii\base\Module::class,
                'controllerNamespace' => 'app\modules\api\controllers',
            ],
        ],
        'components' => [
            'request' => [
                // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
                'cookieValidationKey' => 'wIfgF_wM2saVTt4r2IWoauf2GMz_LUGs',
                'parsers' => [
                    'application/json' => 'yii\web\JsonParser',
                    'multipart/form-data' => 'yii\web\MultipartFormDataParser',
                ],
                'enableCsrfValidation' => false, // для API
            ],

            'response' => [
                'format' => \yii\web\Response::FORMAT_HTML,
            ],

            'queue' => [
                'class' => 'yii\queue\redis\Queue',
                'redis' => 'redis',         // Компонент подключения к Redis
                'channel' => 'queue', // Название канала
                'as log' => \yii\queue\LogBehavior::class,
                'ttr' => 5 * 60, // Максимальное время выполнения задания
                'attempts' => 3, // Максимальное количество попыток

            ],

            'imageManager' => [
                'class' => 'app\components\image\product\ImageManager',
            ],


            'opensearch' => [
                'class' => 'app\components\OpenSearch',
                'hosts' => ['http://opensearch:9200'],
                'index' => 'products',
            ],
            'rabbitmq' => [
                'class' => 'app\components\RabbitMQ\RabbitMQ',
                'host' => getenv('RABBITMQ_HOST') ?: 'rabbitmq', // ← имя сервиса в docker-compose
                'port' => (int)(getenv('RABBITMQ_PORT') ?: 5672),
                'user' => getenv('RABBITMQ_USER') ?: 'guest',
                'password' => getenv('RABBITMQ_PASSWORD') ?: 'guest',
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
                'hostname' => getenv('REDIS_HOST') ?: 'redis',  // или IP сервера Redis ,если разворачиваем через docker то имя ставим redis
                'port' => getenv('REDIS_PORT') ?: 6379,
                'database' => 0,
                'retries' => 3

            ],

            'cache' => [
//            'class' => 'yii\caching\FileCache',
                'class' => 'yii\redis\Cache',
//            'keyPrefix' => 'shop_',

            ],
            'user' => [
                'identityClass' => 'app\models\User',
                'enableAutoLogin' => true,

            ],

            'jwt' => [
                'class' => 'app\components\JwtComponent',
                'key' => 'your-secret-key',
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

            's3Reports' => [
                'class' => 'app\components\filesystem\S3Service',
                'key' => 'minioadmin',
                'secret' => 'minioadmin',
                'bucket' => 'feed-reports', // создайте его вручную в UI или через код
                'endpoint' => 'http://minio:9000',
                'region' => 'us-east-1', // MinIO игнорирует регион, но SDK требует его
                'version' => 'latest',
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
                'traceLevel' => YII_DEBUG ? 3 : 0,
                'targets' => [
                    [
                        'class' => 'yii\log\FileTarget',
                        'levels' => ['error', 'warning', 'info'],
                        'logFile' => '@runtime/logs/app.log',

                    ],
                ],
            ],
            'db' => $db,

            'urlManager' => [
                'enablePrettyUrl' => true,
                'showScriptName' => false,
                'normalizer' => [
                    'class' => yii\web\UrlNormalizer::class,
                    'collapseSlashes' => true,
                    'action' => yii\web\UrlNormalizer::ACTION_REDIRECT_PERMANENT,
                ],


                'rules' => [

                    'api/categories/brands' => 'categories/brands',
                    'GET api/site' => 'site/index',
                    'GET api/ping' => 'site/ping',
                    'GET api/categories/<id:\d+>/brands' => 'categories/brands',
                    'POST api/brands' => 'brands/create',
                    'GET api/<controller:categories|attributes>' => '<controller>/index',
                    'GET api/categories/<id:\d+>' => 'categories/view',
                    'POST api/products' => 'global-products/create',
                    'GET api/products/<id:\d+>' => 'global-products/view',
                    'GET api/products' => 'global-products/index',
                    'GET api/skus' => 'product-skus/index',
                    'GET api/skus/<id:\d+>' => 'product-skus/view',
                    'POST api/skus' => 'product-skus/create',
                    'GET api/categories/<id:\d+>/attributes' => 'categories-attributes/view',
//                'GET api/attributes' => 'attributes/index',
                    'POST api/vendors/register' => 'vendors/register',
                    'GET api/vendors/confirm-email' => 'vendors/confirm-email',
                    'POST api/vendors/login' => 'vendors/login',
                    'POST api/vendors/logout' => 'vendors/logout',
                    'GET api/vendors/me' => 'vendors/me',
                    'POST api/offers/save' => 'offers/save',
                    'GET api/vendors/offers' => 'vendors/offers',
                    'GET api/product/skus/<id:\d+>' => 'product-skus/view',
                    'DELETE api/offers/<id:\d+>' => 'offers/delete',
                    'POST api/vendor/feed/upload' => 'vendor-feed/upload',
                    'GET api/vendor/feed/history' => 'vendor-feed/history',
                    'GET api/vendor/feed/report-status/<id:\d+>' => 'vendor-feed/report-status',
                    'GET api/vendor/feed/template/<categoryId:\d+>' => 'vendor-feed/template',
                    'GET api/categories/<categoryId:\d+>/brands' => 'brands/list',
                    'GET api/vendor-product/get-skus-and-offers' => 'vendor-product/get-skus-and-offers',
                    'POST api/vendor-product/create-or-update' => 'vendor-product/create-or-update',
                    'GET api/vendor-product/get-category-attribute-options' => 'vendor-product/get-category-attribute-options',
                    'GET api/offers/view' => 'offers/view',
                    'GET api/search' => 'search/search',
                    'GET api/search/products' => 'search/search-products',
                    'GET api/search/suggest' => 'search/suggest',
                    'POST api/vendor-product/request-image-upload' => 'vendor-product/request-image-upload',
                    'POST api/vendor-product/confirm-images' => 'vendor-product/confirm-images',
                    'POST api/vendor-product/set-main-image' => 'vendor-product/set-main-image',
                    'GET api/vendor-product/get-images' => 'vendor-product/get-images',




                ],
            ],

        ],
        'params' => $params,
    ];
    $config = yii\helpers\ArrayHelper::merge($config, $diConfig);
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
