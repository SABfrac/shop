<?php

namespace app\services\feed;
use Yii;
use app\jobs\FinalizeFeedReportJob;

class FinalizationService
{
    public static function finalizeReport(int $reportId): void
    {
        $redis = Yii::$app->redis;
        $lockKey = "feed:finalize_lock:{$reportId}";

        // Атомарная блокировка
        if (!$redis->executeCommand('SET', [$lockKey, '1', 'EX', 60, 'NX'])) {
            // Уже выполняется
            return;
        }
        try {
//            1. Завершены ли чанки?
        $totalChunks = (int)($redis->executeCommand('HGET', ["feed:meta:$reportId", 'total_chunks']) ?? 1000);

        $completedChunks = (int)$redis->executeCommand('HGET', ["feed:meta:{$reportId}", 'completed_chunks']);
        $chunksDone = ($completedChunks >= $totalChunks);



            // 2. Завершена ли индексация?
        $expected = (int)($redis->executeCommand('HGET', ["feed:meta:{$reportId}", 'expected_index_jobs']) ?? 0);
        $completed = (int)($redis->executeCommand('HGET', ["feed:meta:{$reportId}", 'completed_index_jobs']) ?? 0);
        $indexingDone = ($expected === 0) || ($completed >= $expected);

        if ($chunksDone && $indexingDone) {
            FinalizeFeedReportJob::handle(['reportId' => $reportId]);
            Yii::info("✅ Finalized report $reportId", __METHOD__);
        }


        } catch (\Throwable $e) {
            Yii::error("💥 Finalization failed for report $reportId: " . $e->getMessage(), __METHOD__);
            throw $e; // или логируем и выходим
        } finally {
            // Опционально: удалить блокировку сразу (или ждать EX 60)
             $redis->executeCommand('DEL', [$lockKey]);
        }
    }

}