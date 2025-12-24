<?php
namespace app\jobs;

use Yii;
use app\models\VendorFeedReports;
use yii\db\Expression;


class FinalizeFeedReportJob
{

    /**
     * @param array $payload Данные из RabbitMQ
     * @throws \Throwable
     */
    public static function handle(array $payload): void
    {
        $reportId = (int)($payload['reportId'] ?? 0);
        if (!$reportId) {
            throw new \RuntimeException("Missing 'reportId'");
        }

        // 1. Получаем основной отчёт (оттуда возьмём started_at)
        $report = VendorFeedReports::findOne($reportId);
        if (!$report) {throw new \RuntimeException("Report $reportId not found");}


        // Агрегируем результаты всех чанков
        $stats = Yii::$app->db->createCommand("
           SELECT 
            COUNT(CASE WHEN status = 'completed' THEN 1 END) AS total_chunks,
            COUNT(CASE WHEN status = 'failed'    THEN 1 END) AS total_failed,
            MAX(created_at) AS last_chunk_at
            FROM feed_chunk_result 
            WHERE report_id = :id
        ", [':id' => $reportId])->queryOne();


        $rawErrors = Yii::$app->redis->executeCommand('LRANGE', ["feed:errors:{$reportId}", 0, 4999]); // Берем первые 5000




        $previewErrors = [];

// формируем .csv
        if (!empty($rawErrors)) {
            $fp = fopen('php://temp', 'r+');
            // BOM для Excel, чтобы кириллица читалась
            fputs($fp, $bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) ));
            fputcsv($fp, ['Row Number', 'SKU', 'Error Message'], ';');

            $count = 0;
            foreach ($rawErrors as $jsonError) {
                $err = json_decode($jsonError, true);

                // Добавляем в CSV
                fputcsv($fp, [
                    $err['line'] ?? '',
                    $err['sku'] ?? '',
                    $err['msg'] ?? ''
                ], ';');

                // Оставим немного для превью в JSON (первые 50 штук)
                if ($count < 2) {
                    $previewErrors[] = $err;
                }
                $count++;
            }

            rewind($fp);
            $csvContent = stream_get_contents($fp);
            fclose($fp);



            // 2. Загружаем в S3
            $fileName = "reports/errors_{$report->vendor_id}_{$reportId}_" . date('YmdHis') . ".csv";

            try {
                // Используем наш сервис
          Yii::$app->s3->upload($fileName, $csvContent,'text/csv', 'feed-reports');


            } catch (\Exception $e) {
                Yii::error("Failed to upload error report: " . $e->getMessage());
                // Не роняем джобу, просто не будет ссылки
            }

        }



        $rawMetrics = Yii::$app->redis->executeCommand('HGETALL', ["feed:metrics:{$reportId}"]);


        $metrics = [];

// Плоский массив: [k1, v1, k2, v2]
        if (is_array($rawMetrics) && array_keys($rawMetrics) === range(0, count($rawMetrics) - 1)) {
            for ($i = 0; $i < count($rawMetrics); $i += 2) {
                if (isset($rawMetrics[$i + 1])) {
                    $metrics[$rawMetrics[$i]] = $rawMetrics[$i + 1];
                }
            }
        } else {
            // Ассоциативный массив: ['k1' => 'v1', 'k2' => 'v2']
            $metrics = $rawMetrics;
        }

        $importTime = isset($metrics['import_time']) ? (float)$metrics['import_time'] : 0.0;
        $indexTime = isset($metrics['index_time']) ? (float)$metrics['index_time'] : 0.0;
        $totalRows = (int)Yii::$app->redis->executeCommand('HGET', ["feed:meta:{$reportId}", 'total_rows']);

        $wallClockTime = 0;
        if ($report->started_at) {
            $lastIndexed = (int)Yii::$app->redis->executeCommand('HGET', ["feed:meta:{$reportId}", 'last_indexed_at']);
            if ($lastIndexed) {
                $startedMoscow = new \DateTime($report->started_at, new \DateTimeZone('Europe/Moscow'));
                $wallClockTime = max(0, $lastIndexed - $startedMoscow->getTimestamp());
            }
        }


        $report->status = VendorFeedReports::STATUS_COMPLETED;

        $report->total_chunks = (int)($stats['total_chunks'] ?? 0);
        $report->errors_json = empty($rawErrors)
            ? json_encode([
                'total_errors' => count($rawErrors),
                'preview'      => array_values($previewErrors),
            ], JSON_UNESCAPED_UNICODE)
            : null;
        $report->total_rows = $totalRows;
        $report->total_failed = count($rawErrors)??null;
        $report->total_duration_sec = $wallClockTime;
        $report->total_indexing_sec = $indexTime;
        $report->file_path=$fileName??null;
        $report->finished_at = $lastIndexed
            ? (new \DateTime())->setTimestamp($lastIndexed)->setTimezone(new \DateTimeZone('Europe/Moscow'))->format('Y-m-d H:i:s')
            : new Expression('NOW()');
        $report->update(false, [
            'status',
            'total_chunks',
            'errors_json',
            'total_rows',
            'total_failed',
            'total_duration_sec',
            'total_indexing_sec',
            'file_path',
            'finished_at',
            'updated_at']);

        Yii::$app->redis->executeCommand('DEL', [
            "feed:metrics:{$reportId}",
            "feed:meta:{$reportId}",
            "feed:finalize_lock:{$reportId}",
            "feed:errors:{$reportId}" ,
        ]);


        Yii::info("Report $reportId finalized. Import: {$importTime}s, Index: {$indexTime }s", __METHOD__);
    }
}
