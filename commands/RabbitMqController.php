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
use OpenSearch\Common\Exceptions\NoNodesAvailableException;
use app\queue\handlers\IndexMessageHandler;



class RabbitMqController extends Controller
{

    /**
     * Запустить воркеры (в отдельных терминалах или через supervisor)
     * docker-compose exec php php yii rabbit-mq/consume-parse
     * docker-compose exec php php yii rabbit-mq/consume-process
     * docker-compose exec php php yii rabbit-mq/consume-index
     */

    /**
     * Инициализация очередей.(обьявляем один раз)
     * docker-compose exec php php yii rabbit-mq/setup-queues
     */
    const QUEUE_PARSE = 'feed.parse';
    const QUEUE_PROCESS = 'feed.process';
    const QUEUE_INDEX = 'feed.index';



    public function actionSetupQueues()
    {
        $rmq = Yii::$app->rabbitmq;

        // Очередь парсинга
        $rmq->declareRetryQueue(
            self::QUEUE_PARSE,
            3,
            5000
        );

        // Очередь обработки чанков
        $rmq->declareRetryQueue(
            self::QUEUE_PROCESS,
            3,
            10000
        );

        $rmq->declareRetryQueue(
            self::QUEUE_INDEX,
            3,
            10000
        );




        $rmq->getChannel()->queue_declare(self::QUEUE_INDEX . '.dlq', false, true, false, false);

        $this->stdout("✅ Все очереди объявлены.\n");
    }

//очереди для отладки

    /**
     * docker-compose exec php php yii rabbit-mq/setup-debug-queues
     */
    public function actionSetupDebugQueues()
    {
        $rmq = Yii::$app->rabbitmq;
        $rmq->declareSimpleQueue(self::QUEUE_PARSE);
        $rmq->declareSimpleQueue(self::QUEUE_PROCESS);
        $rmq->declareSimpleQueue(self::QUEUE_INDEX);
        $rmq->getChannel()->queue_declare(self::QUEUE_INDEX . '.dlq', false, true, false, false);

        $this->stdout("✅ Все очереди объявлены.\n");
    }

    /**
     * Воркер: парсинг фидов
     */
    public function actionConsumeParse()
    {
        $this->stdout("🚀 Запущен consumer для " . self::QUEUE_PARSE . "\n");

        Yii::$app->rabbitmq->consumeWithRetry(self::QUEUE_PARSE, function (AMQPMessage $msg) {
            $data = json_decode($msg->getBody(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Yii::error("❌ Невалидный JSON в " . self::QUEUE_PARSE . ": " . $msg->getBody());
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
        $this->stdout("🚀 Запущен consumer для " . self::QUEUE_PROCESS . "\n");

        Yii::$app->rabbitmq->consumeWithRetry(self::QUEUE_PROCESS, function (AMQPMessage $msg) {
            $body = $msg->getBody();
            Yii::info("📥 Получено сообщение в " . self::QUEUE_PROCESS . ": " . $body);
            $data = json_decode($msg->getBody(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Yii::error("❌ Невалидный JSON в " . self::QUEUE_PROCESS . ": " . $body);
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
        $this->stdout("🚀 Запущен consumer для " . self::QUEUE_INDEX . "\n");

        $maxRetries = 3;
        $handler = Yii::createObject(IndexMessageHandler::class);

        Yii::$app->rabbitmq->consumeWithRetry(self::QUEUE_INDEX, function (AMQPMessage $msg) use ($handler, $maxRetries) {
            return $handler->handle($msg, $maxRetries);
        });

    }


}
