<?php

namespace app\components;

use yii\base\Component;
use app\models\Products;

use app\jobs\PrepareAndSyncProductJob;
use yii\web\NotFoundHttpException;

use app\models\ProductAttributeValues;
use yii;

class SearchSynchronizer extends Component
{

    const SYNC_QUEUE = 'search_sync';
    const RETRY_EXCHANGE = 'search_sync.retry';
    const RETRY_ROUTING_KEY = 'search_sync.retry';


    public function init()
    {
        parent::init();

        // Инициализация очередей с поддержкой retry
        Yii::$app->rabbitmq->declareRetryQueue(
            self::SYNC_QUEUE,
            self::RETRY_EXCHANGE,
            self::RETRY_ROUTING_KEY,
            3,      // max retries
            10000   // 10 sec delay
        );
    }

    public function syncProduct(array $productData, $operation = 'index', int $attempt = 1)
    {
        try {
            if (empty($productData['id'])) {
                throw new \InvalidArgumentException('Invalid product data provided');
            }


            $headers = [
                'x-attempt' => $attempt,
                'x-original-queue' => self::SYNC_QUEUE
            ];



            Yii::$app->rabbitmq->publishMessageWithRetry(
                self::SYNC_QUEUE,
                [
                    'operation' => $operation,
                    'entity_type' => 'product',
                    'entity_id' => $productData['id'],
                    'data' => $productData
                ],
                $headers,
                $this->getMessagePriority($operation)
            );

            return true;
        } catch (\Throwable $e) {
            $productId = $productData['id'] ?? 'unknown';
            Yii::error("Error syncing product {$productId}: " . $e->getMessage());
            $this->markAsFailed($productId, $operation, $e->getMessage());
            return false;
        }
    }

    protected function getMessagePriority($operation)
    {
        // Приоритеты для разных операций
        $priorities = [
            'delete' => 5,    // Удаления выше приоритетом
            'index' => 1      // Индексация ниже
        ];

        return $priorities[$operation] ?? 0;
    }

        protected function markAsFailed($productId, $operation)
        {
            // Помечаем запись как неудачную
            Yii::$app->db->createCommand()
                ->update('sync_queue', [
                    'status' => 2, // Ошибка
                    'processed_at' => new \yii\db\Expression('NOW()')
                ], [
                    'entity_type' => 'product',
                    'entity_id' => $productId,
                    'operation' => $operation
                ])->execute();
        }




}