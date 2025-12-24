<?php
// Для Yii2 Advanced
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/common/config/bootstrap.php';

// Настройка alias для Yii
Yii::setAlias('@common', __DIR__ . '/common');
Yii::setAlias('@frontend', __DIR__ . '/frontend');
Yii::setAlias('@backend', __DIR__ . '/backend');

// Загружаем конфиг (предположим, что вы тестируете frontend)
$config = yii\helpers\ArrayHelper::merge(
    require __DIR__ . '/common/config/main.php',
    require __DIR__ . '/common/config/main-local.php',
    require __DIR__ . '/frontend/config/main.php',
    require __DIR__ . '/frontend/config/main-local.php'
);

// Создаём приложение
(new yii\web\Application($config));

// Теперь можно использовать компоненты
$redis = Yii::$app->redis;
echo 'Redis ping: ' . ($redis->ping() === '+PONG' ? 'OK' : 'FAIL') . "\n";

$cache = Yii::$app->cache;
$cache->set('docker_test_key', 'success', 60);
echo 'Cache read: ' . ($cache->get('docker_test_key') ?: 'MISS') . "\n";
