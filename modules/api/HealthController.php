<?php

namespace app\modules\api\controllers;

use yii\web\Controller;
use yii\web\Response;
use yii\filters\Cors;

class HealthController extends Controller
{
    public function behaviors()
    {
        $b = parent::behaviors();

        // JSON по умолчанию
        $b['contentNegotiator']['formats']['text/html'] = Response::FORMAT_JSON;

        // CORS для Vite
        $b['corsFilter'] = [
            'class' => Cors::class,
            'cors' => [
                'Origin' => ['http://localhost:5173', 'https://localhost'],
                'Access-Control-Request-Method' => ['GET', 'OPTIONS'],
                'Access-Control-Allow-Credentials' => false,
                'Access-Control-Request-Headers' => ['*'],
                'Access-Control-Max-Age' => 86400,
            ],
        ];
        return $b;
    }

    public function actionIndex()
    {
        return ['status' => 'ok', 'ts' => time()];
    }
}