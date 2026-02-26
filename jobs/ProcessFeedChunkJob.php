<?php
namespace app\jobs;
use app\services\offer\OfferBulkImportService;


use Yii;
use app\components\RabbitMQ\AmqpTopology as AMQP;
use yii\db\Exception;
use app\models\VendorFeedReports;
use app\services\feed\FinalizationService;



class ProcessFeedChunkJob
{
    public static function handle(array $payload): void
    {
        Yii::info("🔍 ProcessFeedChunkJob::handle called with payload: " . json_encode($payload, JSON_UNESCAPED_UNICODE));

        $required = ['vendorId', 'categoryId', 'reportId', 'rows'];
        foreach ($required as $key) {
            if (!isset($payload[$key])) {
                throw new \RuntimeException("Missing required field: $key");
            }
        }

        [
            'vendorId' => $vendorId,
            'categoryId' => $categoryId,
            'reportId' => $reportId,
            'rows' => $rows,
        ] = $payload;

        $db = Yii::$app->db;

        try {

            $result = Yii::$container->get(OfferBulkImportService::class)
                ->importChunk($vendorId, $rows, $categoryId, $reportId);


            $db->createCommand()->insert('feed_chunk_result', [
                'report_id' => $reportId,
                'processed_rows' => $result['success'] ?? 0,
                'duration_sec' => $result['metrics']['total']['duration'] ?? 0,
                'errors_json' => !empty($result['errors'])
                    ? json_encode($result['errors'], JSON_UNESCAPED_UNICODE)
                    : null,
                'status' => 'completed',
            ])->execute();


            Yii::$app->rabbitmq->publishWithRetries(
                AMQP::EXCHANGE_EVENTS,
                [[
                    'event' => 'FeedChunkProcessed',
                    'reportId' => $reportId,
                    'chunkData' => [
                        'offer_ids' => $result['offer_ids'] ?? [],
                        'metrics' => $result['metrics'],
                        'processed_rows' => count($rows),
                        'success_count' => $result['success'] ?? 0,
                    ],
                    'timestamp' => time(),
                ]],
                AMQP::RK_FEED_CHUNK_PROCESSED,
            );

        } catch (\Throwable $e) {
            // Логируем ошибку
            $db->createCommand()->insert('feed_chunk_result', [
                'report_id' => $reportId,
                'processed_rows' => 0,
                'errors_json' => json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE),
                'status' => 'failed',
            ])->execute();

            // Публикуем событие ошибки (опционально)
            Yii::$app->rabbitmq->publishWithRetries(
                AMQP::EXCHANGE_EVENTS,
                [[
                    'event' => 'FeedChunkFailed',
                    'reportId' => $reportId,
                    'error' => $e->getMessage(),
                    'timestamp' => time(),
                ]],
                ''
            );

            throw $e;
        }
    }
}
