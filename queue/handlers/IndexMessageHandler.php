<?php

namespace app\queue\handlers;

use app\jobs\OpensearchIndexer;
use PhpAmqpLib\Message\AMQPMessage;
use app\commands\RabbitMqController;
use app\models\VendorFeedReports;
use Yii;
use app\services\feed\FinalizationService;

class IndexMessageHandler
{

    public function __construct(private OpensearchIndexer $indexer)
    {

    }

    public function handle(AMQPMessage $msg, int $maxRetries): bool
    {

        $deathCount = 0;
        if ($msg->has('x-death')) {
            $deaths = $msg->get('x-death')->getNativeData();
            foreach ($deaths as $death) {
                if (($death['queue'] ?? null) === RabbitMqController::QUEUE_INDEX) {
                    $deathCount += (int)($death['count'] ?? 0);
                }
            }
        }

        if ($deathCount >= $maxRetries) {
            $this->publishToDlq($msg);
            return true;
        }

        $data = json_decode($msg->getBody(), true);
        if (!isset($data['offer_ids']) || !is_array($data['offer_ids'])) {
            Yii::warning('Invalid message format', 'rabbitmq');
            return true;
        }


        try {
            $this->indexer->bulkIndexOffers($data['offer_ids'], $data['report_id'] ?? null);

            if ($data['report_id']) {


                Yii::$app->redis->executeCommand('HSET', [
                    "feed:meta:{$data['report_id']}",
                    'last_indexed_at',
                    time()
                ]);


                Yii::$app->redis->executeCommand('HINCRBY', [
                    "feed:meta:{$data['report_id']}",
                    'completed_index_jobs',
                    1
                ]);


                FinalizationService::finalizeReport($data['report_id']);
            }

            return true;
        } catch (\Throwable $e) {
            Yii::error([
                'message' => 'Indexing failed',
                'offer_ids' => $data['offer_ids'],
                'report_id' => $data['report_id'],
                'error' => $e->getMessage(),
                'x_death_count' => $deathCount,
                'trace' => $e->getTraceAsString(),
            ], 'rabbitmq_indexing');

            if ($data['report_id']) {
                Yii::$app->redis->executeCommand('HINCRBY', [
                    "feed:meta:{$data['report_id']}",
                    'completed_index_jobs',
                    1
                ]);

                FinalizationService::finalizeReport($data['report_id']);


            }
            return false;

        }
    }

    private function publishToDlq(AMQPMessage $msg): void
    {
        $dlqMessage = array_merge(
            json_decode($msg->getBody(), true),
            [
                '_dlq_reason' => 'max_retries_exceeded',
                '_dlq_at' => date('c'),
                '_original_x_death' => $msg->has('x-death')
                    ? $msg->get('x-death')->getNativeData()
                    : null
            ]
        );

        Yii::$app->rabbitmq->publishMessageWithRetry(
            RabbitMqController::QUEUE_INDEX . '.dlq', // ← лучше вынести в конфиг
            [$dlqMessage]
        );
    }



}