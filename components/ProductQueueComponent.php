<?php

namespace app\components;

use yii\base\Component;
use yii;
use app\models\FallbackProductBuffer;

class ProductQueueComponent extends Component
{


// Очереди для операций
    const INSERT_QUEUE = 'product_insert_queue';
    const INSERT_RETRY_EXCHANGE = 'product_insert.retry';
    const INSERT_RETRY_ROUTING_KEY = 'product_insert.retry';

    const UPDATE_QUEUE = 'product_update_queue';
    const UPDATE_RETRY_EXCHANGE = 'product_update.retry';
    const UPDATE_RETRY_ROUTING_KEY = 'product_update.retry';


    const INDEX_QUEUE = 'vendor_products_index';
    const INDEX_RETRY_EXCHANGE = 'vendor_products_index.retry';
    const INDEX_RETRY_ROUTING_KEY = 'vendor_products_index.retry';

    private $batch = [];
    private $batchSize = 50; // Количество сообщений в батче
    private $autoFlushInterval = 2; // Автоотправка каждые N секунд, если батч не заполнен
    private $lastFlushTime;

    const INSERT_BUFFER_KEY = 'product_buffer:insert';
    const UPDATE_BUFFER_KEY = 'product_buffer:update';


    public function init()
    {
        parent::init();
        $this->lastFlushTime = time();
        $this->setupInsertQueue();
        $this->setupUpdateQueue();
        $this->setupIndexQueue();

    }

    protected function setupInsertQueue()
    {
        Yii::$app->rabbitmq->declareRetryQueue(
            self::INSERT_QUEUE,
            self::INSERT_RETRY_EXCHANGE,
            self::INSERT_RETRY_ROUTING_KEY,
            3,
            10000
        );
    }

    protected function setupUpdateQueue()
    {
        Yii::$app->rabbitmq->declareRetryQueue(
            self::UPDATE_QUEUE,
            self::UPDATE_RETRY_EXCHANGE,
            self::UPDATE_RETRY_ROUTING_KEY,
            3,
            10000
        );
    }


    protected function setupIndexQueue()
    {
        Yii::$app->rabbitmq->declareRetryQueue(
            self::INDEX_QUEUE,
            self::INDEX_RETRY_EXCHANGE,
            self::INDEX_RETRY_ROUTING_KEY,
            3,
            10000
        );
    }


    public function enqueueBulkProduct($productData)
    {

        $insertPayloads = [];
        $updatePayloads = [];
        $now = time();

        foreach ($productData as $product) {
            $item = [
                'seller_id' => Yii::$app->user->id,
                'product' => $product,
                'created_at' => $now
            ];

            empty($product['id']) ? $insertPayloads[] = $item : $updatePayloads[] = $item;
        }
        try {

            if (!empty($insertPayloads)) {
                $jsonPayloads = array_map('json_encode', $insertPayloads);
                Yii::$app->redis->rpush(self::INSERT_BUFFER_KEY, ...$jsonPayloads);
            }
            if (!empty($updatePayloads)) {
                $jsonPayloads = array_map('json_encode', $updatePayloads);
                Yii::$app->redis->rpush(self::UPDATE_BUFFER_KEY, ...$jsonPayloads);
            }
        } catch (\Exception $e) {
            Yii::error("Redis unavailable: " . $e->getMessage());
            // Fallback: сохраняем в БД
            $this->saveToFallbackDb($insertPayloads, $updatePayloads);
        }
    }

    private function saveToFallbackDb($insertPayloads, $updatePayloads)
    {
        foreach ($insertPayloads as $item) {
            $model = new FallbackProductBuffer();
            $model->type = 'insert';
            $model->payload = json_encode($item);
            $model->created_at = time();
            $model->save(false);
        }
        foreach ($updatePayloads as $item) {
            $model = new FallbackProductBuffer();
            $model->type = 'update';
            $model->payload = json_encode($item);
            $model->created_at = time();
            $model->save(false);
        }

    }

    /**
     * Периодически (или по событию) отдельный процесс должен проверять, есть ли записи в fallback_product_buffer,
     * и отправить в Redis, когда он снова доступен
     */

    public function actionRetryFallback()
    {
        $items = FallbackProductBuffer::find()->limit(100)->all();
        foreach ($items as $item) {
            try {
                $key = $item->type === 'insert' ? self::INSERT_BUFFER_KEY : self::UPDATE_BUFFER_KEY;
                Yii::$app->redis->rpush($key, $item->payload);
                $item->delete();
            } catch (\Exception $e) {
                Yii::error("Retry to Redis failed: " . $e->getMessage());
                // Оставляем запись для следующей попытки
            }
        }


    }
}