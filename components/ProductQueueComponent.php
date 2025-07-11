<?php
namespace app\components;

use yii\base\Component;
use yii;

class ProductQueueComponent extends Component
{
    const SYNC_QUEUE = 'vendor_products_queue';
    const SYNC_RETRY_EXCHANGE = 'vendor_products.retry';

    const SYNC_RETRY_ROUTING_KEY = 'vendor_products.retry';

    const INDEX_QUEUE = 'vendor_products_index';
    const INDEX_RETRY_EXCHANGE = 'vendor_products_index.retry';
    const INDEX_RETRY_ROUTING_KEY = 'vendor_products_index.retry';

    private $batch = [];
    private $batchSize = 50; // Количество сообщений в батче
    private $autoFlushInterval = 5; // Автоотправка каждые N секунд, если батч не заполнен
    private $lastFlushTime;



    public function init()
    {
        parent::init();


        $this->setupSyncQueue();
        $this->setupIndexQueue();

    }


    protected function setupSyncQueue()
    {
        Yii::$app->rabbitmq->declareRetryQueue(
            self::SYNC_QUEUE,
            self::SYNC_RETRY_EXCHANGE,
            self::SYNC_RETRY_ROUTING_KEY,
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
        $this->batch[] = [
            'seller_id' => Yii::$app->user->id,
            'products' => $productData,
            'created_at' => time()
        ];

        if (count($this->batch) >= $this->batchSize||
            (time() - $this->lastFlushTime) > $this->autoFlushInterval) {
            $this->flushBatchWithRetry();
            $this->lastFlushTime = time();
        }

        return true;
    }


    /**
     * Принудительная отправка накопленных сообщений с переотправкой до 3 раз
     */
    public function flushBatchWithRetry()
    {
        try {
            Yii::$app->rabbitmq->publishWithRetries(
                self::SYNC_QUEUE,
                $this->batch,
                ['x-retry-count' => 0]
            );
            $this->batch = []; // Очищаем буфер
        } catch (\Throwable $e) {
            Yii::error("Failed to flush batch: " . $e->getMessage());
            // Можно добавить повторную попытку или логику восстановления
        }
    }







}
