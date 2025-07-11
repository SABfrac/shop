<?php
namespace app\services;

use Yii;
use app\models\Products;

class ProductBatchService
{
    private $batchSize = 1000; // или любой другой размер

    public function processBatch(array &$products)
    {
        $this->batchInsertNewProducts($products);
        $this->batchUpdateExistingProducts($products);
        $this->pushToIndexingQueue(array_column($products, 'id'));
    }

    private function batchInsertNewProducts(array &$products)
    {
        try {
            $newProducts = array_filter($products, fn($p) => empty($p['id']));

            if (!empty($newProducts)) {
                $chunkSize = 1000; // Размер чанка
                foreach (array_chunk($newProducts, $chunkSize) as $chunk) {
                    $columns = array_keys(reset($chunk));
                    Yii::$app->db->createCommand()
                        ->batchInsert(Products::tableName(), $columns, $chunk)
                        ->execute();

                }
            }
        } catch (\Exception $e) {
            Yii::error("Ошибка вставки новых продуктов: " . $e->getMessage(), __METHOD__);
            throw $e;
        }
    }


    private function batchUpdateExistingProducts(array $products)
    {
        $db = Yii::$app->db;
        $transaction = $db->beginTransaction();

        try {
            $tempTable = 'temp_product_updates_' . time();

            $columns = array_keys(reset($products));
            $columnsSql = implode(', ', array_map(fn($col) => "$col TEXT", $columns));
            $createTempTableSql = "CREATE TEMP TABLE $tempTable (id INT PRIMARY KEY, $columnsSql) ON COMMIT DROP";
            $db->createCommand($createTempTableSql)->execute();

            $chunkSize = 1000; // Размер чанка
            foreach (array_chunk($products, $chunkSize) as $chunk) {
                // Вставка данных по чанкам
                $db->createCommand()->batchInsert($tempTable, ['id'] + $columns, $chunk)->execute();

                // Обновление по чанкам
                $setClause = implode(', ', array_map(fn($col) => "p.$col = t.$col", $columns));
                $updateSql = "UPDATE " . Products::tableName() . " p
                          SET $setClause
                          FROM $tempTable t
                          WHERE p.id = t.id";

                $db->createCommand($updateSql)->execute();
            }

            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            Yii::error("Ошибка обновления продуктов: " . $e->getMessage(), __METHOD__);
            throw $e;
        }
    }



    private function pushToIndexingQueue(array $productIds)
    {
        try {
            // Разбиваем массив ID на чанки, чтобы не перегружать очередь
            $chunkSize = 1000;
            foreach (array_chunk($productIds, $chunkSize) as $chunk) {
                // Отправляем чанк ID в очередь индексации
                Yii::$app->queue->push(new IndexProductsJob([
                    'productIds' => $chunk
                ]));

                // Или альтернативный вариант, если используется другая система очередей:
                // Yii::$app->searchQueue->addToIndexQueue($chunk);
            }
        } catch (\Exception $e) {
            Yii::error("Ошибка при добавлении в очередь индексации: " . $e->getMessage(), __METHOD__);
            throw $e;
        }
    }

}

