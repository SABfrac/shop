<?php

namespace app\jobs\listeners;
use Yii;
use PhpAmqpLib\Message\AMQPMessage;



class MetricsListener
{
    public function handle(AMQPMessage $msg)
    {
        $data = json_decode($msg->getBody(), true);

        if ($data['event'] !== 'FeedChunkProcessed') {
            return true;
        }
        try {
        if ($data['event'] === 'FeedChunkProcessed') {

            $successCount = $chunkData['success_count'] ?? 0;
            $duration = $data['chunkData']['metrics']['total']['duration'] ?? 0;
            Yii::$app->redis->executeCommand('HINCRBYFLOAT', ["feed:metrics:{$data['reportId']}", 'import_time', $duration]);
            Yii::$app->redis->executeCommand('HINCRBY', ["feed:meta:{$data['reportId']}", 'success_count', $successCount] ?? 0);
            Yii::$app->redis->executeCommand('HINCRBY', ["feed:meta:{$data['reportId']}", 'completed_chunks', 1]);
            if (!empty($data['chunkData']['offer_ids'])) {
                Yii::$app->redis->executeCommand('HINCRBY', ["feed:meta:{$data['reportId']}", 'expected_index_jobs', 1]);
            }
        }
            return true;
        } catch (\Throwable $e) {
            Yii::error("💥 Ошибка в MetricsListener: " . $e->getMessage());
            return false;
        }
    }
}