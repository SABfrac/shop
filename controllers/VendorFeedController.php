<?php

namespace app\controllers;
use app\traits\VendorAuthTrait;
use app\models\FeedChunkResul;
use yii\web\Controller;
use yii\web\UploadedFile;
use yii\web\BadRequestHttpException;
use app\models\VendorFeedReports;
use app\models\Categories;
use Yii;
use yii\web\Response;
use yii\web\NotFoundHttpException;
use app\components\RabbitMQ\AmqpTopology as AMQP;


class VendorFeedController extends Controller
{

    use VendorAuthTrait;

    /**
     * запуск воркера для очереди вручную  docker-compose exec php php yii queue/listen
     */
    public function actionUpload()
    {
        $categoryId = (int)Yii::$app->request->post('category_id');
        $vendorId = $this->getAuthorizedVendorId();
        $file = UploadedFile::getInstanceByName('feed');

        $category = Categories::findOne($categoryId);
        if (!$category || !$category->is_leaf) {
            throw new BadRequestHttpException('Выбранная категория должна быть листовой');
        }

        if (!$file || !in_array($file->extension, ['csv', 'json'])) {
            throw new BadRequestHttpException('Требуется CSV или JSON файл');
        }



//         🔥 Создаём отчёт сразу — без total_rows
        $report = new VendorFeedReports();
        $report->vendor_id = $vendorId;
        $report->status = VendorFeedReports::STATUS_QUEUED;
        $report->total_chunks = 0; // или 0, если поле NOT NULL
        $report->save();

        if (!$report->save()) {
            return $this->asJson(['errors' => $report->errors]);
        }

        $tempPath = Yii::getAlias("@runtime/uploads/feed_{$vendorId}_{$report->id}.{$file->extension}");
        if (!$file->saveAs($tempPath)) {
            throw new \RuntimeException('Не удалось сохранить временный файл');
        }

        Yii::$app->rabbitmq->publishWithRetries(
            '',
            [
                [
                    'tempFilePath' => $tempPath,
                    'vendorId' => $vendorId,
                    'categoryId' => $categoryId,
                    'reportId' => $report->id,
                    'fileExtension' => $file->extension,
                ]

            ],
            AMQP::QUEUE_PARSE,
        );

        return $this->asJson(['reportId' => $report->id,
                              'status' => 'queued'
        ]);
    }


    public function actionReportStatus($id)
    {
        $report = VendorFeedReports::findOne(['id' => $id, 'vendor_id' => $this->getAuthorizedVendorId()]);


        if (!$report) {
            throw new NotFoundHttpException('Report not found');
        }
        $isFinished = in_array($report->status, ['completed', 'completed_with_errors', 'failed']);

        if (!$isFinished) {

        $meta = Yii::$app->redis->executeCommand('HGETALL', ["feed:meta:{$id}"]);
        // Преобразуем плоский массив Redis [key, val, key, val] в ассоциативный
        $metaData = [];
        for ($i = 0; $i < count($meta); $i += 2) {
            $metaData[$meta[$i]] = $meta[$i + 1];
        }

        $totalChunks = (int)($metaData['total_chunks'] ?? 0);
        $completedChunks = (int)($metaData['completed_chunks'] ?? 0);



        $progressPercent = 0;
        if ($totalChunks > 0) {
            $progressPercent = round(($completedChunks / $totalChunks) * 100, 1);
        }

        $etaSeconds = null;
        if ($report->started_at && $completedChunks > 2) { // Ждем немного данных для точности
            $startedAt = strtotime($report->started_at);
            $elapsed = time() - $startedAt;
            if ($progressPercent > 0) {
                $estimatedTotal = $elapsed / ($progressPercent / 100);
                $etaSeconds = max(0, $estimatedTotal - $elapsed);
            }
        }


            return $this->asJson([
                'isFinished' => false,
                'progressPercent' => $progressPercent,
                'etaSeconds' => $etaSeconds ? (int)$etaSeconds : null,
            ]);
        }

        $errorCount = $report->total_failed ?? 0;
        $totalRows = (int)$report->total_rows;
        $successCount = max(0, $totalRows - $errorCount);





        // Вычисляем общее время (если есть started_at и finished_at)
        $totalElapsed = null;
        if ($report->started_at && $report->finished_at) {
            $totalElapsed = (new \DateTime($report->finished_at))->getTimestamp()
                - (new \DateTime($report->started_at))->getTimestamp();
        }

        return $this->asJson([
            'status' => $report->status,
            'successCount' => $successCount,
            'errors' => $report->errors_json ? json_decode($report->errors_json, true) : null,
            'errorFileUrl' => $report->file_path
                ? Yii::$app->s3Reports->getPresignedUrl(
                    $report->file_path,
                    '+1 hour',
                     'feed-reports',
                    'GET',
                    'http://localhost:9000',
                    )
                : null,
            'totalRows'=>(int)($report->total_rows ?? 0),
            'isFinished' => ($report->finished_at !== null),
            'progressPercent' => 100,
            'etaSeconds' => null,

            'metrics' => [
                'importTime' => (float)$report->total_duration_sec,
                'indexTime' => (float)$report->total_indexing_sec,
                'totalElapsed' => $totalElapsed ? (float)$totalElapsed : null,
            ]
        ]);
    }

    /**
     * Возвращает историю загрузок фидов для текущего вендора.
     *
     * @return array
     */
    public function actionHistory()
    {
        $vendorId = $this->getAuthorizedVendorId();

        $reports = VendorFeedReports::find()
            ->select([
                'id',
                'vendor_id',
                'status',
                'total_rows',
                'file_path',
                'created_at'
            ])
            ->where(['vendor_id' => $vendorId])
            ->orderBy(['created_at' => SORT_DESC])
            ->limit(20)
            ->asArray()
            ->all();

        $items = [];
        foreach ($reports as $report) {
            // Определяем текст статуса
            $statusText = match ($report['status']) {
                'completed' => 'Успешно',
                'completed_with_errors' => 'Завершено с ошибками',
                'failed' => 'Ошибка',
                'processing' => 'В обработке',
                default => $report['status']
            };

            $items[] = [
                'id' => (int)$report['id'],
                'successCount' => (int)($report['total_rows'] ?? 0),
                'status' => $report['status'],
                'hasErrorReport' => !empty($report['file_path']),
                'errorFileUrl' => $report['file_path'] ?? null,
                'statusText' => $statusText,
                'createdAt' => $report['created_at'],
            ];
        }

        return $this->asJson([
            'items' => $items
        ]);
    }

    /**
     * Метод подготавливает  csv шаблон с  заголовками (заголовки из таблицы offers +  заголовки вариативных атрибутов товара) и отдает
     * на фронт пустой csv файл c добавленными заголовками  ,в зависимости от той категории товара что выбрал продавец на фронте будут меняться
     * заголовки вариативных атрибутов в выгружаемом шаблоне
     * @param int $categoryId
     * @return false|string|Response
     *
     */
    public function actionTemplate(int $categoryId)
    {
        $category = Categories::findOne($categoryId);
        if (!$category) {
            throw new NotFoundHttpException('Категория не найдена.');
        }

        // Если запрошен CSV-шаблон
        if (Yii::$app->request->get('download')) {
            // Получаем атрибуты категории
            $attributes = $category->getFeedAttributeSchema(); // должен возвращать массив строк

            // Базовые поля
            $headers = ['sku_code', 'product_name', 'brand', 'price', 'stock','warranty'];

            // Добавляем атрибуты категории
            $headers = array_merge($headers, $attributes);

            // Генерируем CSV в памяти
            $output = fopen('php://temp', 'w+');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $headers,';');
            rewind($output);
            $csvContent = stream_get_contents($output);
            fclose($output);

            // Настройка ответа
            Yii::$app->response->format = Response::FORMAT_RAW;
            Yii::$app->response->headers->set('Content-Type', 'text/csv; charset=utf-8');
            Yii::$app->response->headers->set('Content-Disposition', 'attachment; filename="feed_template.csv"');

            return $csvContent;
        }

        // Иначе — возвращаем JSON с данными категории (для API)
        return $this->asJson([
            'id' => $category->id,
            'name' => $category->name,
            // можно добавить и другие поля, если нужно
        ]);

    }









}