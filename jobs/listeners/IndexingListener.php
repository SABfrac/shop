<?php

namespace app\jobs\listeners;
use Yii;
use PhpAmqpLib\Message\AMQPMessage;
use app\components\RabbitMQ\AmqpTopology as AMQP;

class IndexingListener
{
    public function handle(AMQPMessage $msg)
    {
        $data = json_decode($msg->getBody(), true);

        if ($data['event'] !== 'FeedChunkProcessed') {
            return true;
        }

        $reportId = $data['reportId'];
        $offerIds = $data['chunkData']['offer_ids'] ?? [];
        if (empty($offerIds)) {
            Yii::info("⚠️ Нет офферов для индексации в отчете $reportId", __METHOD__);
            return true;
        }

        try {
            // ✅ Публикуем ЗАДАЧУ в очередь с retry/DLQ
            Yii::$app->rabbitmq->publishWithRetries(
                '',
                [
                    [
                        'offer_ids' => $offerIds,
                        'report_id' => $reportId,
                    ]
                ],
                AMQP::QUEUE_INDEX,
            );


            return true;
        } catch (\Throwable $e) {
            Yii::error("💥 Ошибка в IndexingListener: " . $e->getMessage());
            return false; // NACK без повтора
        }
    }

}



