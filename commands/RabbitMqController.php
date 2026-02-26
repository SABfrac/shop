<?php

namespace app\commands;

use app\jobs\ProcessFeedChunkJob;
use Yii;
use yii\console\Controller;
use PhpAmqpLib\Message\AMQPMessage;
use app\jobs\ParseFeedJob;
use app\jobs\FinalizeFeedReportJob;
use app\models\VendorFeedReports;
use app\jobs\OpensearchIndexer;
use app\jobs\IndexOffersJob;
use app\jobs\listeners\IndexingListener;
use app\jobs\listeners\MetricsListener;
use app\jobs\listeners\FinalizationListener;
use app\components\RabbitMQ\AmqpTopology as AMQP;


class RabbitMqController extends Controller
{
    /**
     * Управление RabbitMQ: очереди, обменники, воркеры
     *
     * Архитектура событий:
     * 1. ProcessFeedChunkJob обрабатывает чанк → публикует событие FeedChunkProcessed
     * 2. events_direct (direct exchange) маршрутизирует по routing key:
     *    - feed.chunk.processed → [MetricsListener, IndexingListener]
     *    - feed.finalized        → [FinalizationListener]
     * 3. IndexingListener → публикует задачу в feed.index
     * 4. IndexOffersJob → индексирует → обновляет метрики → публикует событие
     * 5. FinalizationListener → проверяет условия(что все пачки фидов обработаны) → запускает FinalizeFeedReportJob
     *
     * Запуск воркеров:
     *   docker-compose exec app php yii rabbit-mq/consume-parse
     *   docker-compose exec app php yii rabbit-mq/consume-process
     *   docker-compose exec app php yii rabbit-mq/consume-index
     *   docker-compose exec app php yii rabbit-mq/consume-indexing-listener
     *   docker-compose exec app php yii rabbit-mq/consume-metrics-listener
     *   docker-compose exec app php yii rabbit-mq/consume-finalization-listener
     *
     * Инициализация:
     *   docker-compose exec app php yii rabbit-mq/setup          # Отладочные очереди с слушателями событий
     *   docker-compose exec app php yii rabbit-mq/setup-queues   # Только основные очереди (для production)
     *   docker-compose exec app php yii rabbit-mq/setup-debug-queues # Отладочные очереди
     */


    /**
     * Настройка всех очередей и обменников (отладка)
     * использование : docker-compose exec app php yii rabbit-mq/setup
     * @param bool $debug Включить отладочные очереди (без retry и DLX политики)
     */
    public function actionSetup()
    {
        $rmq = Yii::$app->rabbitmq;
        $channel = $rmq->getChannel();

        try {
            $channel->exchange_delete(AMQP::EXCHANGE_EVENTS);
            $this->stdout("🗑️ Старый обменник '" . AMQP::EXCHANGE_EVENTS . "' удален.\n");
        } catch (\Throwable $e) {
            // Игнорируем ошибку, если обменника не существует
            $this->stdout("!бменник '" . AMQP::EXCHANGE_EVENTS . "' не найден (или уже удален).\n");
        }

        // Настройка основных очередей для обработки фидов
        $this->actionSetupDebugQueues();

        // Настройка событийной топологии
        $this->setupEventTopology($channel);

        $this->stdout("✅ Все очереди и обменники объявлены.\n");
    }

    public function actionSetupQueues()
    {
        $rmq = Yii::$app->rabbitmq;
        $rmq->declareSimpleQueue(AMQP::QUEUE_PARSE);
        $rmq->declareSimpleQueue(AMQP::QUEUE_PROCESS);
        $rmq->declareSimpleQueue(AMQP::QUEUE_INDEX);
        $rmq->getChannel()->queue_declare(AMQP::QUEUE_INDEX_DLQ, false, true, false, false);
        $this->stdout("✅ Все очереди объявлены.\n");
    }


    /**
     * docker-compose exec app php yii rabbit-mq/setup-debug-queues
     */
    public function actionSetupDebugQueues()
    {
        $rmq = Yii::$app->rabbitmq;
        $rmq->declareSimpleQueue(AMQP::QUEUE_PARSE);
        $rmq->declareSimpleQueue(AMQP::QUEUE_PROCESS);
        $rmq->declareSimpleQueue(AMQP::QUEUE_INDEX);
        $rmq->getChannel()->queue_declare(AMQP::QUEUE_INDEX_DLQ, false, true, false, false);
        $this->stdout("✅ Все очереди объявлены.\n");

    }

    public function setupEventTopology($channel)
    {
        // Direct обменник — маршрутизирует по точному совпадению routing key
        $channel->exchange_declare(
            AMQP::EXCHANGE_EVENTS,
            'direct',
            false,
            true,
            false
        );
        $this->stdout("  📡 Обменник: " . AMQP::EXCHANGE_EVENTS . " (direct)\n");

        // Привязка слушателей
        $this->bindQueue($channel, AMQP::QUEUE_METRICS_LISTENER, AMQP::RK_FEED_CHUNK_PROCESSED);
        $this->bindQueue($channel, AMQP::QUEUE_INDEXING_LISTENER, AMQP::RK_FEED_CHUNK_PROCESSED);
        $this->bindQueue($channel, AMQP::QUEUE_FINALIZATION_LISTENER, AMQP::RK_FEED_FINALIZED);

        $this->stdout("  🔗 Все очереди привязаны к " . AMQP::EXCHANGE_EVENTS . "\n");
    }

    private function bindQueue($channel, string $queue, string $routingKey): void
    {
        $channel->queue_declare($queue, false, true, false, false);
        $channel->queue_bind($queue, AMQP::EXCHANGE_EVENTS, $routingKey);
        $this->stdout("  👂 Слушатель: $queue (routing_key: $routingKey)\n");
    }


    /**
     * Воркер: парсинг фидов
     */
    public function actionConsumeParse()
    {
        $this->stdout("🚀 Запущен consumer для " . AMQP::QUEUE_PARSE . "\n");

        Yii::$app->rabbitmq->consumeWithRetry(AMQP::QUEUE_PARSE, function (AMQPMessage $msg) {
            $data = json_decode($msg->getBody(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Yii::error("❌ Невалидный JSON в " . AMQP::QUEUE_PARSE . ": " . $msg->getBody());
                return true; // → nack → retry
            }

            try {
                ParseFeedJob::handle($data);
                Yii::info("✅ ParseFeedJob успешно завершён (reportId={$data['reportId']})");
                return true;
            } catch (\Throwable $e) {
                $reportId = $data['reportId'] ?? 'unknown';
                Yii::error("💥 ParseFeedJob упал (reportId=$reportId): " . $e->getMessage());


                // Обновляем статус отчёта на 'failed', если возможно
                if ($reportId !== 'unknown') {
                    VendorFeedReports::updateAll([
                        'status' => VendorFeedReports::STATUS_FAILED,
                        'errors_json' => mb_substr($e->getMessage(), 0, 500, 'UTF-8'),
                    ], ['id' => $reportId]);
                }
                return false;

            }
        });
    }

    /**
     * Воркер: обработка чанков
     */
    public function actionConsumeProcess()
    {
        $this->stdout("🚀 Запущен consumer для " . AMQP::QUEUE_PROCESS . "\n");

        Yii::$app->rabbitmq->consumeWithRetry(AMQP::QUEUE_PROCESS, function (AMQPMessage $msg) {
            $body = $msg->getBody();
            Yii::info("📥 Получено сообщение в " . AMQP::QUEUE_PROCESS . ": " . $body);
            $data = json_decode($msg->getBody(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Yii::error("❌ Невалидный JSON в " . AMQP::QUEUE_PROCESS . ": " . $body);
                return true;
            }

            try {
                ProcessFeedChunkJob::handle($data);
                Yii::info("✅ ProcessFeedChunkJob завершён (reportId={$data['reportId']})");
                return true;
            } catch (\Throwable $e) {
                $reportId = $data['reportId'] ?? 'unknown';
                Yii::error("💥 ProcessFeedChunkJob упал (reportId=$reportId): " . $e->getMessage());
                return false; // → retry через DLX
            }
        });
    }


    public function actionConsumeIndex()
    {
        $this->stdout("🚀 Запущен consumer для " . AMQP::QUEUE_INDEX . "\n");


        Yii::$app->rabbitmq->consumeWithRetry(AMQP::QUEUE_INDEX, function (AMQPMessage $msg) {
            $data = json_decode($msg->getBody(), true);


            if (json_last_error() !== JSON_ERROR_NONE) {
                Yii::error("❌ Невалидный JSON в " . AMQP::QUEUE_INDEX . ": " . $msg->getBody());
                return true; // ACK — битое сообщение не ретраим
            }

            try {

                IndexOffersJob::handle($data);
                return true;
            } catch (\Throwable $e) {
                Yii::error("💥 IndexOffersJob упал: " . $e->getMessage());

                return false;
            }
        });

    }

    /**
     * Воркер: слушатель индексации
     * запуск: docker-compose exec app php yii rabbit-mq/consume-indexing-listener
     */

    public function actionConsumeIndexingListener()
    {
        $this->stdout("👂 Запущен слушатель индексации...\n");

        // Инстанцируем через контейнер для DI
        $listener = Yii::createObject(IndexingListener::class);

        Yii::$app->rabbitmq->consumeSimple(
            AMQP::QUEUE_INDEXING_LISTENER,
            fn(AMQPMessage $msg) => $listener->handle($msg)
        );
    }

    /**
     * Воркер: слушатель метрик
     * запуск: docker-compose exec app php yii rabbit-mq/consume-metrics-listener
     */
    public function actionConsumeMetricsListener()
    {
        $this->stdout("📊 Запущен слушатель метрик для " . AMQP::QUEUE_METRICS_LISTENER . "\n");

        $listener = Yii::createObject(MetricsListener::class);

        Yii::$app->rabbitmq->consumeSimple(
            AMQP::QUEUE_METRICS_LISTENER,
            fn(AMQPMessage $msg) => $listener->handle($msg)
        );
    }

    /**
     * Воркер: слушатель финализации
     * запуск: docker-compose exec app php yii rabbit-mq/consume-finalization-listener
     */
    public function actionConsumeFinalizationListener()
    {
        $this->stdout("📊 Запущен слушатель метрик для " . AMQP::QUEUE_FINALIZATION_LISTENER . "\n");

        $listener = Yii::createObject(FinalizationListener::class);

        Yii::$app->rabbitmq->consumeSimple(
            AMQP::QUEUE_FINALIZATION_LISTENER,
            fn(AMQPMessage $msg) => $listener->handle($msg)
        );
    }


}
