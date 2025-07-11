<?php

namespace app\commands;
use app\traits\ProductDataPreparer;
use yii\console\Controller;
use PhpAmqpLib\Message\AMQPMessage;
use app\components\ProductQueueComponent;
use yii;
use app\models\Products;

class ProductIndexerCommand
{

    use ProductDataPreparer;

    public function actionRun()
    {
        $callback = function (AMQPMessage $msg) {
            $productIds = json_decode($msg->getBody(), true);

            try {
                // Оптимизированная загрузка данных
                $products = $this->loadProductsWithEav($productIds);

                if (empty($products)) {
                    return true;
                }

                // Формируем документы для OpenSearch
                $documents = [];
                foreach ($products as $product) {
                    $documents[] = [
                        'index' => [
                            '_index' => 'products',
                            '_id' => $product->id,
                        ]
                    ];
                    $documents[] = $this->prepareProductData($product);
                }

                // Пакетная отправка в OpenSearch
                $this->sendBatch($documents);

                return true;
            } catch (\Exception $e) {
                Yii::error("Ошибка индексации: " . $e->getMessage());
                return false;
            }
        };

        Yii::$app->rabbitmq->consumeWithRetry(ProductQueueComponent::INDEX_QUEUE, $callback);
    }

    protected function loadProductsWithEav(array $productIds)
    {
        return Products::find()
            ->with([
                'vendor',
                'category',
                'brand',
                'productAttributeValues.attributeOption',
                'productFlat',
                'productAttributeValues.productAttribute'
            ])
            ->where(['id' => $productIds])
            ->asArray()
            ->all();
    }


    protected function sendBatch(&$documents) //передаем массив по прямой ссылке для экономии памяти если без & то передавалась бы копия массива
    {
        try {
            Yii::$app->opensearch->bulk($documents);
        } catch (\Exception $e) {
            Yii::error("Bulk operation failed: " . $e->getMessage());
            return false; // Вернет false, что вызовет retry через DLX
        }
        return true;
    }


}