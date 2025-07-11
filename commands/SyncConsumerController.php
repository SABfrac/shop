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
        $limit = 1000;
        $batchSize = 300;
        $maxRetries = 3;
        $processedItems = [];
        $bulkActions = [];
        $batchStartTime = microtime(true);
        $batchTimeout = 5; // секунды

        $callback = function (AMQPMessage $msg) use (
            &$processedItems,
            &$bulkActions,
            &$batchStartTime,
            $batchSize,
            $batchTimeout
        ){
            $data = json_decode($msg->getBody(), true);
            $headers = $msg->has('application_headers') ?
                $msg->get('application_headers')->getNativeData() : [];

            // Проверяем количество попыток
            $attempt = $headers['x-attempt'] ?? 1;
            if ($attempt > 3) {
                Yii::error("Message failed after 3 attempts: " . json_encode($data));
                $msg->ack(); // Удаляем сообщение после 3 попыток
                return true;
            }

            try {
                // Bulk-операции
                if ($data['operation'] === 'index') {
                    $bulkActions[] = ['index' => ['_id' => $data['entity_id']]];
                    $bulkActions[] = $data['data'];
                } elseif ($data['operation'] === 'delete') {
                    $bulkActions[] = ['delete' => ['_id' => $data['entity_id']]];
                }

                $processedItems[] = $data;

                $currentTime = microtime(true);
                $elapsedTime = $currentTime - $batchStartTime;

                // Проверка условий отправки батча
                if (count($bulkActions) >= $batchSize || $elapsedTime >= $batchTimeout) {
                    // Отправка батча
                    if (!$this->sendBatch($bulkActions)) {
                        throw new \RuntimeException("Batch failed");
                    }

                }
                $bulkActions = [];
                $batchStartTime = microtime(true); // сброс таймера

                // Логирование обработанных элементов
                if (count($processedItems) >= $batchSize) {
                    $this->logProcessedItems($processedItems);
                    $processedItems = [];
                }
                $msg->ack();
                return true;
            } catch (\Throwable $e) {
                Yii::error("Ошибка обработки: " . $e->getMessage(), 'consumer');
                $msg->nack(true); // отправим обратно
                return false;
            }
        };

        Yii::$app->rabbitmq->consumeWithRetry(
            SearchSynchronizer::SYNC_QUEUE,
            $callback,
            50 // лимит сообщений которые может взять потребитель из очереди до подтверждения
        );

        // Отправка оставшихся данных
        if (!empty($bulkActions)) {
            $this->sendBatch($bulkActions);
        }

        if (!empty($processedItems)) {
            $this->logProcessedItems($processedItems);
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
