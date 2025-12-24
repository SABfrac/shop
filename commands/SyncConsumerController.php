<?php

namespace app\commands;


use yii\console\Controller;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use yii;
use app\components\SearchSynchronizer;


/**
 * запуск команды php yii sync-consumer/index
 * 1)Инициализация переменных:
 *
 * $limit = 1000; — максимальное количество сообщений, которое будет обработано за один запуск.
 * $batchSize = 150; — количество операций в одной пачке отправки в OpenSearch.
 * $batchTimeout = 5; — отправлять батч не только по количеству, но и если прошло 5 секунд.
 * $processedItems, $bulkActions — сбор обработанных сообщений и действий для bulk-запроса.
 * $maxRetries = 3 — максимальное количество повторов при ошибках подключения к RabbitMQ.
 *
 * 2) Определение callback-функции обработки одного сообщения:
 *
 * Распаковывается JSON ($data = json_decode(...)).
 * В зависимости от операции:
 * index: добавляется команда index для OpenSearch и сами данные.
 * delete: добавляется команда delete по _id.
 * Проверяется, нужно ли отправлять bulk (по количеству или по времени).
 * Обработанные сообщения логируются (может быть запись в лог-файл или базу).
 * ack() — подтверждает получение сообщения в RabbitMQ.
 *
 *3) Цикл с повторными попытками:
 *
 * Используется try-catch, чтобы:
 * Пытаться подключаться и читать из очереди.
 * В случае ошибки AMQPConnectionClosedException пробовать снова с задержкой (экспоненциальная задержка до 30 секунд).
 *
 * 4)После завершения обработки:
 * Отправляется оставшийся незавершённый bulk.
 * Логируются оставшиеся необработанные данные.
 *
 *
 */
class SyncConsumerController extends Controller
{

    public function actionIndex()
    {
        echo "Запуск синхронного потребителя для поиска...\n";
        $batchSize = 300;
        $maxRetries = 3;
        $processedItems = [];
        $bulkActions = [];
        $pendingAcks = []; // копим сообщения для ack после успешного batch
        $batchStartTime = microtime(true);
        $batchTimeout = 5; // секунды

        $callback = function (AMQPMessage $msg) use (
            &$processedItems,
            &$bulkActions,
            &$pendingAcks,
            &$batchStartTime,
            $batchSize,
            $batchTimeout,
            $maxRetries
        ){
            $data = json_decode($msg->getBody(), true) ?? [];
            $headers = $msg->has('application_headers')
                ? $msg->get('application_headers')->getNativeData()
                : [];

            // Проверяем количество попыток
            $attempt = $headers['x-attempt'] ?? 1;
            if ($attempt > $maxRetries) {
                Yii::error("Message failed after {$maxRetries} attempts: " . json_encode($data));
                $msg->ack(); // удаляем окончательно
                return true;
            }

            try {
                // Bulk-операции
                if (($data['operation'] ?? null) === 'index') {
                    $bulkActions[] = ['index' => ['_id' => $data['entity_id']]];
                    $bulkActions[] = $data['data'];
                } elseif (($data['operation'] ?? null) === 'delete') {
                    $bulkActions[] = ['delete' => ['_id' => $data['entity_id']]];
                } else {
                    throw new \RuntimeException('Unknown operation');
                }

                // Кладём текущее сообщение в очередь на подтверждение после успешного sendBatch
                $pendingAcks[] = $msg;
                $processedItems[] = $data;

                $elapsedTime = microtime(true) - $batchStartTime;
                $needFlush = (count($bulkActions) >= $batchSize) || ($elapsedTime >= $batchTimeout);

                if ($needFlush) {
                    // Отправка батча
                    if (!$this->sendBatch($bulkActions)) {
                        throw new \RuntimeException("Batch failed");
                    }

                    // ACK всех сообщений, вошедших в батч
                    foreach ($pendingAcks as $m) {
                        $m->ack();
                    }
                    $pendingAcks = [];

                    // Логирование обработанных элементов (после успешного flush)
                    if (!empty($processedItems)) {
                        $this->logProcessedItems($processedItems);
                        $processedItems = [];
                    }

                    // Сброс батча и таймера только после удачного flush
                    $bulkActions = [];
                    $batchStartTime = microtime(true);
                }

                return true;
            } catch (\Throwable $e) {
                Yii::error("Ошибка обработки: " . $e->getMessage(), 'consumer');

                // Гарантируем, что текущее сообщение тоже вернём, если его ещё нет в pendingAcks
                $found = false;
                foreach ($pendingAcks as $m) {
                    if ($m === $msg) { $found = true; break; }
                }
                if (!$found) {
                    $pendingAcks[] = $msg;
                }

                // NACK всех сообщений текущего «висящего» батча (с requeue)
                foreach ($pendingAcks as $m) {
                    $m->nack(true);
                }
                $pendingAcks = [];

                // Сбрасываем несостоявшийся батч
                $bulkActions = [];
                $processedItems = [];
                $batchStartTime = microtime(true);

                return false;
            }
        };

        Yii::$app->rabbitmq->consumeWithRetry(
            SearchSynchronizer::SYNC_QUEUE,
            $callback,
            50 // prefetch
        );

        // Финальный flush при остановке потребителя
        if (!empty($bulkActions)) {
            if ($this->sendBatch($bulkActions)) {
                foreach ($pendingAcks as $m) {
                    $m->ack();
                }
                $pendingAcks = [];
                $bulkActions = [];

                if (!empty($processedItems)) {
                    $this->logProcessedItems($processedItems);
                    $processedItems = [];
                }
            } else {
                foreach ($pendingAcks as $m) {
                    $m->nack(true);
                }
                $pendingAcks = [];
            }
        }
    }


    protected function sendBatch(&$bulkActions) //передаем массив по прямой ссылке для экономии памяти если без & то передавалась бы копия массива
    {
        try {
            Yii::$app->opensearch->bulk($bulkActions);
        } catch (\Exception $e) {
            Yii::error("Bulk operation failed: " . $e->getMessage());
            return false; // Вернет false, что вызовет retry через DLX
        }
        return true;
    }


}
