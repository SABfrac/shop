<?php
namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

class OpenSearchController extends Controller
{
    /**
     * Создает индекс с маппингом
     * проверка запущен ли opensearch : docker-compose exec app  curl http://opensearch:9200
     * Использование: docker exec app php yii open-search/create-index
     */
    public function actionCreateIndex()
    {
        $opensearch = Yii::$app->opensearch;

        // Проверяем, существует ли индекс
        if ($opensearch->getClient()->indices()->exists(['index' => $opensearch->index])) {
            $this->stdout("⚠️  Индекс '{$opensearch->index}' уже существует.\n", Console::FG_YELLOW);

            if ($this->confirm('Удалить и создать заново?')) {
                $opensearch->deleteIndex();
                $this->stdout("🗑️  Индекс удален.\n", Console::FG_RED);
            } else {
                return ExitCode::OK;
            }
        }

        try {
            $opensearch->createIndex();
            $this->stdout("✅ Индекс '{$opensearch->index}' успешно создан!\n", Console::FG_GREEN);
            return ExitCode::OK;
        } catch (\Exception $e) {
            $this->stderr("❌ Ошибка: " . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Удаляет индекс
     * Использование: docker-compose exec app php yii open-search/delete-index
     */
    public function actionDeleteIndex()
    {
        if ($this->confirm('Вы уверены, что хотите удалить индекс?')) {
            Yii::$app->opensearch->deleteIndex();
            $this->stdout("✅ Индекс удален.\n", Console::FG_GREEN);
        }
        return ExitCode::OK;
    }

    /**
     * Показывает статистику индекса
     * Использование: docker-compose exec app php yii open-search/status
     */
    public function actionStatus()
    {
        $opensearch = Yii::$app->opensearch;
        $client = $opensearch->getClient();

        if (!$client->indices()->exists(['index' => $opensearch->index])) {
            $this->stdout("❌ Индекс '{$opensearch->index}' не существует.\n", Console::FG_RED);
            return ExitCode::OK;
        }

        // Получаем статистику
        $stats = $client->indices()->stats(['index' => $opensearch->index]);
        $count = $client->count(['index' => $opensearch->index]);

        $this->stdout("\n📊 Статистика индекса '{$opensearch->index}':\n", Console::FG_CYAN);
        $this->stdout("   Документов: {$count['count']}\n");
        $this->stdout("   Размер: " . $this->formatBytes($stats['_all']['primaries']['store']['size_in_bytes']) . "\n");

        return ExitCode::OK;
    }

    /**
     * Полная переиндексация всех офферов
     * Использование: docker-compose exec app php yii open-search/reindex-all
     */
    public function actionReindexAll($batchSize = 500)
    {
        $this->stdout("🔄 Начинаем полную переиндексацию...\n", Console::FG_CYAN);

        $query = \app\models\Offers::find()
            ->where(['status' => \app\models\Offers::STATUS_ACTIVE])
            ->orderBy(['id' => SORT_ASC]);

        $total = $query->count();
        $processed = 0;

        foreach ($query->batch($batchSize) as $offers) {
            $ids = array_column($offers, 'id');

            // Ставим в очередь
            Yii::$app->queue->push(new \app\jobs\OpensearchIndexer([
                'offer_ids' => $ids,
            ]));

            $processed += count($ids);
            $this->stdout("   Поставлено в очередь: {$processed}/{$total}\r");
        }

        $this->stdout("\n✅ Все задачи поставлены в очередь!\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }



    /**
     * Удаляет неактивные документы из индекса OpenSearch, обновлённые более указанного количества дней назад.
     *
     * @param int $hours Количество часов назад (по умолчанию 3)
     * @return int
     * Использование: docker-compose exec app php yii open-search/inactive-offers
     */
    public function actionInactiveOffers(int $minutes = 1): int
    {
        $index = Yii::$app->opensearch->index;
        $client = Yii::$app->opensearch->getClient();

        $this->stdout("⏰ Текущее время сервера: " . date('c') . "\n");


        $this->stdout("🔍 Поиск неактивных документов в индексе '{$index}', обновлённых более {$minutes} часолв назад...\n");

        // Формируем запрос: is_active = false AND updated_at <= now - $days
        $query = [
            'bool' => [
                'must' => [
                    ['term' => ['is_active' => false]],
                    ['range' => ['updated_at' => ['lte' => "now-{$minutes}m"]]],
                    ],
                ],
            ];

        $count = $client->count(['index' => $index, 'body' => ['query' => $query]])['count'] ?? 0;

        if ($count === 0) {
            $this->stdout("✅ Не найдено неактивных документов для удаления.\n", Console::FG_GREEN);
            return self::EXIT_CODE_NORMAL;
        }

        $this->stdout("🗑️ Найдено {$count} документов. Удаление...\n", Console::FG_YELLOW);

        try {
            $response = $client->deleteByQuery([
                'index' => $index,
                'body'  => ['query' => $query],
                'refresh' => true,
                'timeout' => '5m',
            ]);

            $deleted = $response['deleted'] ?? 0;
            $this->stdout("✅ Удалено {$deleted} документов.\n", Console::FG_GREEN);

            Yii::info("OpenSearch: удалено {$deleted} неактивных документов (старше {$minutes} минут)", 'opensearch');

            return self::EXIT_CODE_NORMAL;
        } catch (\Exception $e) {
            $this->stderr("❌ Ошибка: " . $e->getMessage() . "\n", Console::FG_RED);
            Yii::error("OpenSearch cleanup failed: " . $e->getMessage(), 'opensearch');
            return self::EXIT_CODE_ERROR;
        }
    }
}
