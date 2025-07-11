<?php

namespace app\commands;


use yii\console\Controller;
use app\components\ProductQueueComponent;
use PhpAmqpLib\Message\AMQPMessage;
use yii;
use app\jobs\PrepareAndSyncProductJob;


class ProductWorkerCommand extends Controller
{
    public function actionRun()
    {
        $callback = function (AMQPMessage $msg) {
            $data = json_decode($msg->getBody(), true);
            $products = $data['products'];
            $insertedIds = [];

            try {
                // 1. Подготовка данных для UPSERT
                $rows = [];
                foreach ($products as $product) {
                    $rows[] = [
                        'name' => $product['name'],
                        'price' => $product['price'],
                        'vendor_id' => $product['vendor_id'],
                        'updated_at' => new \yii\db\Expression('NOW()'),
                    ];//должны быть все столбцы таблицы доработать????
                }

                // 2. Пакетный UPSERT (PostgreSQL)

                $transaction = Yii::$app->db->beginTransaction();

                try {
                    $sql = Yii::$app->db->queryBuilder->batchInsert('products', [
                        'external_id', 'name', 'price', 'vendor_id', 'updated_at'
                    ], $rows);
// разобрать как работает  ON CONFLICT должен быть уникальные данные вместо external_id возможно vendor_id
                    $sql .= " ON CONFLICT (external_id) DO UPDATE SET
                    name = EXCLUDED.name,
                    price = EXCLUDED.price,
                    vendor_id = EXCLUDED.vendor_id,
                    updated_at = EXCLUDED.updated_at
                    RETURNING id";

                    $processedIds = Yii::$app->db->createCommand($sql)->queryColumn();
                    $transaction->commit();
                    } catch (\Exception $e) {
                        $transaction->rollBack();
                        throw $e;
                    }


                $this->enqueueProductIds($processedIds);

                return true;
            } catch (\Exception $e) {
                Yii::error("Ошибка обработки товаров: " . $e->getMessage());
                return false; // Retry
            }
        };

        Yii::$app->rabbitmq->consumeWithRetry(ProductQueueComponent::SYNC_QUEUE, $callback);
    }

    protected function enqueueProductIds(array $productIds)
    {
        // Разделение на батчи, если необходимо
        $batchSize = 1000;
        foreach (array_chunk($productIds, $batchSize) as $chunkIds) {
            Yii::$app->rabbitmq->publishMessageWithRetry(
                ProductQueueComponent::INDEX_QUEUE,
                $this->$productIds,//что конкретно отправлять доработать
                ['x-retry-count' => 0]
            );
        }
    }









}