<?php
namespace app\jobs;


use app\components\RabbitMQ\AmqpTopology as AMQP;

use Yii;

class IndexOffersJob
{
    public static function handle(array $payload): void
    {
        $required = ['offer_ids'];
        foreach ($required as $key) {
            if (!isset($payload[$key])) {
                throw new \RuntimeException("Missing required field: $key");
            }
        }

        $offerIds = $payload['offer_ids'];
        $reportId = $payload['report_id'] ?? null;

        if (empty($offerIds)) {
            Yii::warning("Пустой список офферов для индексации", __METHOD__);
            return;
        }

        Yii::info("🔍 Начинаем индексацию " . count($offerIds) . " офферов" .
            ($reportId ? " для отчёта $reportId" : ""), __METHOD__);

        try {
            $startTime = microtime(true);

            // Индексация через сервис
            Yii::$container->get(OpensearchIndexer::class)
                ->bulkIndexOffers($offerIds, $reportId);

            $duration = microtime(true) - $startTime;

            // ✅ Обновляем метрики (только если есть report_id)
            if ($reportId) {
                Yii::$app->redis->executeCommand('HINCRBYFLOAT', [
                    "feed:metrics:{$reportId}",
                    'index_time',
                    $duration
                ]);

                Yii::$app->redis->executeCommand('HINCRBY', [
                    "feed:meta:{$reportId}",
                    'completed_index_jobs',
                    1
                ]);

                Yii::$app->redis->executeCommand('HSET', [
                    "feed:meta:{$reportId}",
                    'last_indexed_at',
                    time()
                ]);
            }

            Yii::info("✅ Индексация завершена (" . count($offerIds) . " офферов, " .
                round($duration, 3) . "s)" . ($reportId ? " для отчёта $reportId" : ""), __METHOD__);
            Yii::$app->redis->executeCommand('DEL', ["feed_report_status:{$reportId}"]);


            // ✅ Публикуем событие (для мониторинга и триггера финализации)
            if ($reportId) {
                Yii::$app->rabbitmq->publishWithRetries(
                    AMQP::EXCHANGE_EVENTS,
                    [[
                        'event' => 'FeedChunkIndexed',
                        'reportId' => $reportId,
                        'offerCount' => count($offerIds),
                        'duration' => $duration,
                        'timestamp' => time(),
                    ]],
                    AMQP::RK_FEED_FINALIZED,
                );
            }

        } catch (\Throwable $e) {
            Yii::error("💥 Ошибка индексации: " . $e->getMessage(), __METHOD__);
            Yii::error("Стек: " . $e->getTraceAsString(), __METHOD__);

            // ✅ Выбрасываем исключение — попадёт в DLQ через consumeWithRetry
            throw $e;
        }
    }
}