<?php
namespace app\jobs;

use app\commands\RabbitMqController;
use app\components\RabbitMQ\AmqpTopology as AMQP;
use app\models\VendorFeedReports;
use app\services\feed\CsvFeedParser;
use app\services\feed\JsonFeedParser;
use RuntimeException;
use Yii;
use yii\db\Expression;

class ParseFeedJob
{
    /**
     * @param array $payload Данные из RabbitMQ
     * @throws \Throwable
     */
    public static function handle(array $payload): void
    {
        $required = ['tempFilePath', 'vendorId', 'categoryId', 'reportId', 'fileExtension'];
        foreach ($required as $key) {
            if (!isset($payload[$key])) {
                throw new RuntimeException("Missing required field: $key");
            }
        }

        [
            'tempFilePath' => $tempFilePath,
            'vendorId' => $vendorId,
            'categoryId' => $categoryId,
            'reportId' => $reportId,
            'fileExtension' => $fileExtension,
        ] = $payload;

        // Обновляем статус отчёта
        $updated = VendorFeedReports::updateAll(
            ['status' => VendorFeedReports::STATUS_PARSING],
            ['id' => $reportId]
        );

        if ($updated === 0) {
            Yii::warning("Report $reportId not found or already processed.");
            return;
        }

        $chunkCount = 0;


        if ($fileExtension === 'csv') {

            $totalRows =  (new CsvFeedParser())->streamParse($tempFilePath, function (array $chunk) use ($vendorId, $categoryId, $reportId, &$chunkCount) {
                $chunkCount  ++;
                $message = [
                    'vendorId' => (int)$vendorId,
                    'categoryId' => (int)$categoryId,
                    'reportId' => (int)$reportId,
                    'rows' => $chunk, // ← 1000 строк
                ];

                Yii::$app->rabbitmq->publishWithRetries(
                    '',
                    [$message],// ← обёртка обязательна!
                    AMQP::QUEUE_PROCESS,
                );
            });

        } elseif ($fileExtension === 'json') {
            $rows = (new JsonFeedParser())->parse($tempFilePath);
            $chunks = array_chunk($rows, 5000);
            $chunkCount = count($chunks);
            $messages = array_map(fn($chunk) => [
                'vendorId' => (int)$vendorId,
                'categoryId' => (int)$categoryId,
                'reportId' => (int)$reportId,
                'rows' => $chunk,
            ], $chunks);

            if (!empty($messages)) {
                Yii::$app->rabbitmq->publishWithRetries('', $messages,AMQP::QUEUE_PROCESS);
            }
        }else {
            throw new RuntimeException("Unsupported file extension: $fileExtension");
        }

        Yii::$app->redis->executeCommand('HSET', [
            "feed:meta:{$reportId}",
            'total_chunks',
            $chunkCount,
            'total_rows',
            $totalRows ,

        ]);


        if (!empty($messages)) {
            Yii::$app->rabbitmq->publishWithRetries(
                '',
                $messages,
                AMQP::QUEUE_PROCESS,
            );
        }

        // Обновляем общее количество чанков
        VendorFeedReports::updateAll([
            'total_chunks' =>  $chunkCount,
            'started_at' => new Expression('NOW()'),
            'total_rows'=>  $totalRows,
            'status' => VendorFeedReports::STATUS_CHUNKS_QUEUED,
        ], ['id' => $reportId]);
//    finally { можно обернуть если упадет CsvFeedParser() то файл удалиться
        // Удаляем временный файл
        if (file_exists($tempFilePath)) {
            unlink($tempFilePath);
            Yii::info("Temporary file deleted: $tempFilePath", __METHOD__);
        }
//      }
    }
}
