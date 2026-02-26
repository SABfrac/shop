<?php

namespace app\services\feed;
use Yii;
use app\jobs\FinalizeFeedReportJob;

use app\components\RabbitMQ\AmqpTopology as AMQP;

class FinalizationService
{
    /**
     * Проверяет, можно ли финализировать отчёт
     *
     * @return array ['canFinalize' => bool, 'reason' => string, 'details' => array]
     */
    public static function canFinalize(int $reportId): array
    {
        // 1. Проверяем статус отчёта (уже завершён?)
        $reportStatus = Yii::$app->db->createCommand(
            "SELECT status FROM vendor_feed_reports WHERE id = :id",
            [':id' => $reportId]
        )->queryScalar();

        if ($reportStatus === \app\models\VendorFeedReports::STATUS_COMPLETED) {
            return [
                'canFinalize' => false,
                'reason' => 'already_completed',
                'details' => ['status' => $reportStatus]
            ];
        }

        // 2. Проверяем: все ли чанки обработаны?
        $totalChunks = (int)Yii::$app->db->createCommand(
            "SELECT total_chunks FROM vendor_feed_reports WHERE id = :id",
            [':id' => $reportId]
        )->queryScalar();

        $completedChunks = (int)(Yii::$app->redis->executeCommand('HGET', [
            "feed:meta:{$reportId}",
            'completed_chunks'
        ]) ?? 0);

        $chunksDone = ($completedChunks >= $totalChunks);

        if (!$chunksDone) {
            return [
                'canFinalize' => false,
                'reason' => 'chunks_not_ready',
                'details' => [
                    'completed' => $completedChunks,
                    'expected' => $totalChunks,
                ]
            ];
        }

        // 3. Проверяем: завершена ли индексация?
        $expectedIndexJobs = (int)(Yii::$app->redis->executeCommand('HGET', [
            "feed:meta:{$reportId}",
            'expected_index_jobs'
        ]) ?? 0);

        $completedIndexJobs = (int)(Yii::$app->redis->executeCommand('HGET', [
            "feed:meta:{$reportId}",
            'completed_index_jobs'
        ]) ?? 0);

        $indexingDone = ($expectedIndexJobs === 0) || ($completedIndexJobs >= $expectedIndexJobs);

        if (!$indexingDone) {
            return [
                'canFinalize' => false,
                'reason' => 'indexing_not_ready',
                'details' => [
                    'completed' => $completedIndexJobs,
                    'expected' => $expectedIndexJobs,
                ]
            ];
        }

        // ✅ Все условия выполнены
        return [
            'canFinalize' => true,
            'reason' => 'ready',
            'details' => [
                'chunks' => "$completedChunks/$totalChunks",
                'indexing' => "$completedIndexJobs/$expectedIndexJobs",
            ]
        ];
    }

    /**
     * Выполняет финализацию отчёта (вызывает джобу)
     *
     * @return bool true если финализация запущена
     */
    public static function finalizeReport(int $reportId): bool
    {
        $check = self::canFinalize($reportId);

        if (!$check['canFinalize']) {
            Yii::info("❌ Нельзя финализировать отчёт $reportId: " . $check['reason'], __METHOD__);
            return false;
        }

        try {
            // Запускаем финальную джобу
         FinalizeFeedReportJob::handle(['reportId' => $reportId]);

            Yii::info("✅ Отчёт $reportId успешно финализирован", __METHOD__);
            Yii::$app->redis->executeCommand('DEL', ["feed_report_status:{$reportId}"]);
            // Публикуем событие о завершении
            Yii::$app->rabbitmq->publishWithRetries(
                AMQP::EXCHANGE_EVENTS,
                [[
                    'event' => 'FeedReportFinalized',
                    'reportId' => $reportId,
                    'timestamp' => time(),
                ]],
                AMQP::RK_FEED_FINALIZED ,
            );

            return true;
        } catch (\Throwable $e) {
            Yii::error("💥 Ошибка финализации отчёта $reportId: " . $e->getMessage());
            throw $e;
        }
    }

}