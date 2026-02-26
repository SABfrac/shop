<?php

namespace app\jobs\listeners;
use Yii;
use PhpAmqpLib\Message\AMQPMessage;
use app\commands\RabbitMqController;
use app\services\feed\FinalizationService;

class FinalizationListener
{
    public function handle(AMQPMessage $msg)
    {
        $data = json_decode($msg->getBody(), true);

        if (!in_array($data['event'], ['FeedChunkFailed','FeedChunkIndexed'])) {
            return true;
        }

        $reportId = $data['reportId'];
        $lockKey = "feed:finalize_lock:{$reportId}";
        $redis = Yii::$app->redis;


            try {
                // 🔒 Атомарная блокировка (60 сек таймаут)
                // Предотвращает параллельные запуски финализации
                if (!$redis->executeCommand('SET', [$lockKey, '1', 'EX', 60, 'NX'])) {
                    Yii::info("🔒 Финализация уже выполняется для отчёта $reportId", __METHOD__);
                    return true;
                }

                // ✅ ВЫЗЫВАЕМ СЕРВИС вместо дублирования логики
                $result = FinalizationService::finalizeReport($reportId);

                if ($result) {
                    Yii::info("🎉 Финализация запущена для отчёта $reportId", __METHOD__);
                } else {
                    Yii::info("⏳ Ждём завершения всех чанков/индексации для отчёта $reportId", __METHOD__);
                }

            } catch (\Throwable $e) {
                Yii::error("💥 Критическая ошибка в FinalizationListener для отчёта $reportId: " . $e->getMessage());
                Yii::error("Стек: " . $e->getTraceAsString());
            } finally {
                // Снимаем блокировку
                $redis->executeCommand('DEL', [$lockKey]);
            }

            return true;
        }
}