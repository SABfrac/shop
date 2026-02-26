<?php
namespace app\controllers;
use app\traits\VendorAuthTrait;
use Yii;
use yii\web\Controller;
use yii\filters\ContentNegotiator;
use yii\filters\VerbFilter;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use app\models\VendorFeedReports;
use yii\filters\Cors;
class FeedImportSseController extends Controller
{
    use VendorAuthTrait;

    public $enableCsrfValidation = false;

    public function behaviors()
    {
        return [
            'corsFilter' => [
                'class' => Cors::class,
                'cors' => [
                    // ВАЖНО: Нельзя использовать '*' с 'Access-Control-Allow-Credentials: true'
                    'Origin' => ['https://localhost:5173', 'http://localhost:5173'], // Укажите точные адреса фронтенда
                    'Access-Control-Request-Method' => ['GET', 'POST', 'OPTIONS'],
                    'Access-Control-Request-Headers' => ['*'],
                    // ЭТО КЛЮЧЕВОЙ МОМЕНТ: разрешаем отправку cookie/заголовков авторизации
                    'Access-Control-Allow-Credentials' => true,
                    'Access-Control-Max-Age' => 3600,
                    // Явно указываем разрешенные источники, а не '*'
                    'Access-Control-Allow-Origin' => ['https://localhost:5173', 'http://localhost:5173'],
                ],
            ],
        ];
    }

    /**
     * Streams events for a specific report ID.
     * URL: /feed-import-sse/stream?reportId=123
     */
    public function actionStream(int $reportId)
    {
        $vendorId = $this->getAuthorizedVendorId();

        if (!$vendorId) {
            \Yii::$app->response->setStatusCode(403);
            return \Yii::$app->response->data = json_encode(['error' => 'Неавторизованный доступ']);
        }

        $report = VendorFeedReports::findOne($reportId);
        if (!$report || $report->vendor_id !== $vendorId) {
            \Yii::$app->response->setStatusCode(404);
            return \Yii::$app->response->data = json_encode(['error' => 'Отчёт не найден или недоступен']);
        }

        // Отключаем автоматическую отправку заголовков Yii
        \Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        \Yii::$app->response->headers->set('Content-Type', 'text/event-stream');
        \Yii::$app->response->headers->set('Cache-Control', 'no-cache');
        \Yii::$app->response->headers->set('Connection', 'keep-alive');
        \Yii::$app->response->headers->set('X-Accel-Buffering', 'no');

        // Очищаем буфер вывода
        if (ob_get_level() > 0) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
        }

        // Отправляем начальное сообщение
        echo "event: connected\n";
        echo "data: " . json_encode([
                'status' => 'connected',
                'reportId' => $reportId
            ]) . "\n\n";
        flush();

        $channel = "feed:import:{$reportId}";
        $redis = \Yii::$app->redis;

        try {
            // Подписываемся на канал
            $redis->subscribe([$channel], function ($redisInstance, $channelName, $message) {
                \Yii::info("Redis message received: " . $message, 'sse-debug');

                $payload = json_decode($message, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($payload)) {
                    echo "event: import_update\n";
                    echo "data: " . json_encode($payload) . "\n\n";
                    flush();
                } else {
                    \Yii::error("Invalid JSON received from Redis: " . $message, 'sse-debug');
                }
            });
        } catch (\Throwable $e) {
            \Yii::error([
                'message' => 'SSE stream error',
                'exception' => get_class($e),
                'error' => $e->getMessage(),
            ], 'sse-error');
        } finally {
            // Финальное сообщение
            echo "event: closed\n";
            echo "data: " . json_encode(['status' => 'closed']) . "\n\n";
            flush();
        }

        // Завершаем выполнение приложения
        \Yii::$app->end();
    }
}