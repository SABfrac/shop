<?php

namespace app\controllers;

use yii\rest\Controller;

class TestController extends Controller
{
    public function actionIndex()
    {
        return ['status' => 'OK', 'time' => date('c')];
    }
}