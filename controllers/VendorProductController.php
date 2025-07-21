<?php

namespace app\controllers;

use app\models\ProductForm;
use yii\web\Controller;

use Yii;

class VendorProductController extends Controller
{

    public function actionCreate()
    {
        $model = new ProductForm();
        $products = Yii::$app->request->post();
        // load() автоматически заполняет свойства модели из $_POST
        // validate() запускает проверку по правилам из rules()
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {

            // На этом этапе $model - это наш ВАЛИДИРОВАННЫЙ DTO!

            // Получаем все атрибуты модели в виде массива
            $payload = $model->getAttributes();



            Yii::$app->productQueue->enqueueBulkProduct($payload);
            // Сериализуем и отправляем в очередь


            return $this->asJson(['status' => 'ok', 'message' => 'Товар поставлен в очередь на обработку.']);

        } else {
            // Если данные невалидны, возвращаем ошибки
            return $this->asJson(['status' => 'error', 'errors' => $model->getErrors()]);
        }
    }

}