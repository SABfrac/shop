<?php
namespace app\jobs;
use app\services\offer\OfferBulkImportService;


use Yii;
use app\commands\RabbitMqController;
use yii\db\Exception;
use app\models\VendorFeedReports;
use app\services\feed\FinalizationService;


class ProcessFeedChunkJob
{
    /**
     * @param array $payload Данные из RabbitMQ
     * @throws \Throwable
     */
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
//        $transaction = $db->beginTransaction();

        try {
//
//            $service = new OfferBulkImportService();
//            $result = $service->importChunk($vendorId, $rows, $categoryId, $reportId);
            $result = Yii::$container->get(OfferBulkImportService::class)->importChunk($vendorId, $rows, $categoryId, $reportId);

            $duration = $result['metrics']['total']['duration'] ?? 0;


// Пишем в Redis — это O(1), <0.1 мс
            Yii::$app->redis->executeCommand('HINCRBYFLOAT', ["feed:metrics:{$reportId}", 'import_time', $duration]);


            $db->createCommand()->insert('feed_chunk_result', [
                'report_id' => $reportId,
                'processed_rows' => $result['success'] ?? 0,
                'duration_sec' => $duration ?? 0,
                'errors_json' => !empty($result['errors'])
                    ? json_encode($result['errors'], JSON_UNESCAPED_UNICODE)
                    : null,
                'status' => 'completed',
            ])->execute();



//            $transaction->commit();
            if (!empty($result['offer_ids'])) {
                Yii::$app->rabbitmq->publishWithRetries(
                    RabbitMqController::QUEUE_INDEX,
                    [
                        ['offer_ids' => $result['offer_ids'],
                            'report_id' => $reportId, // для логирования
                        ]
                    ]
                );

                Yii::$app->redis->executeCommand('HINCRBY', ["feed:meta:{$reportId}", 'expected_index_jobs', 1]);
            }

            $completedChunks = Yii::$app->redis->executeCommand('HINCRBY', ["feed:meta:{$reportId}", 'completed_chunks', 1]);

            $expectedChunks = (int)Yii::$app->db->createCommand(
                "SELECT total_chunks FROM vendor_feed_reports WHERE id = :id", [':id' => $reportId]
            )->queryScalar();

            // Если это последний чанк — пытаемся финализировать
            if ($completedChunks >= $expectedChunks) {
                FinalizationService::finalizeReport($reportId);
            }


        } catch (\Throwable $e) {
//            $transaction->rollBack();

            $db->createCommand()->insert('feed_chunk_result', [
                'report_id' => $reportId,
                'processed_rows' => 0,
                'errors_json' => json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE),
                'status' => 'failed',
            ])->execute();

            $expectedChunks = (int)Yii::$app->db->createCommand(
                "SELECT total_chunks FROM vendor_feed_reports WHERE id = :id", [':id' => $reportId]
            )->queryScalar();

            // Если это последний чанк — пытаемся финализировать
            if ($completedChunks >= $expectedChunks) {
                FinalizationService::finalizeReport($reportId);
            }


            throw $e;
        }
    }


}
