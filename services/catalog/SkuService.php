<?php

namespace app\services\catalog;

use app\models\ProductSkus;
use app\services\ProductSkuVariantHashBuilder;
use Yii;
use yii\db\Connection;
use yii\db\Query;

/**
 * Сервис для работы с ProductSku.
 */
class SkuService
{

    private ProductSkuVariantHashBuilder $hashBuilder; // Предполагается, что это класс из вашего кода

    public function __construct( ProductSkuVariantHashBuilder $hashBuilder)
    {

        $this->hashBuilder = $hashBuilder;
    }

    /**
     * Создает новый SKU.
     *
     * @param int $globalProductId
     * @param array $variantAttributes Атрибуты варианта (структура для хранения).
     * @param string $variantHash Хеш варианта.
     * @param string|null $code Код SKU.
     * @param string|null $barcode Штрихкод.
     * @return ProductSkus
     * @throws \Exception
     */


    /**
     * Bulk insert для SKUs, отправляем пачками.
     *
     * @param array $skuDataToInsert
     * @return array [key => sku_id, ...]
     */
    public function bulkInsertSkus(array $skuDataToInsert): array
    {
        if (empty($skuDataToInsert)) {
            return [];
        }

        $uniqueSkuData = $this->deduplicateSkuData($skuDataToInsert);
        if (empty($uniqueSkuData)) {
            return [];
        }

        $chunkSize = 1000;

        // Вставляем чанками
        foreach (array_chunk($uniqueSkuData, $chunkSize) as $chunk) {
            $this->insertSkuChunk($chunk);
        }

        // Получаем все ID за один запрос
        $result = $this->fetchSkuIds($uniqueSkuData, ProductSkus::tableName());

        Yii::info([
            'action' => 'bulkInsertSkus',
            'unique' => count($uniqueSkuData),
            'output' => count($result),
        ], 'performance');

        return $result;
    }


    private function insertSkuChunk(array $chunk): void
    {
        if (empty($chunk)) {
            return;
        }

        $db = Yii::$app->db;
        $psTable = ProductSkus::tableName();

        $placeholders = [];
        $params = [];
        foreach ($chunk as $idx => $item) {
            $placeholders[] = sprintf(
                '(:gp%d, :vh%d, :vv%d::jsonb, :st%d)',
                $idx, $idx, $idx, $idx
            );
            $params[":gp{$idx}"] = $item['global_product_id'];
            $params[":vh{$idx}"] = $item['variant_hash'];
            $params[":vv{$idx}"] = $item['variant_values'];
            $params[":st{$idx}"] = $item['status'];
        }

        $sql = "
        INSERT INTO {$psTable} (global_product_id, variant_hash, variant_values, status)
        VALUES " . implode(', ', $placeholders) . "
        ON CONFLICT (global_product_id, variant_hash) DO NOTHING
    ";

        $db->createCommand($sql, $params)->execute();
    }

    /**
     * Дедупликация SKU данных.
     *
     * @param array $skuData
     * @return array
     */
    private function deduplicateSkuData(array $skuData): array
    {
        $unique = [];
        $seen = [];
        foreach ($skuData as $item) {
            $key = $item['global_product_id'] . '|' . $item['variant_hash'];
            if (isset($seen[$key])) {
                continue;
            }
            $unique[] = $item;
            $seen[$key] = true;
        }
        return $unique;
    }

    /**
     * Получает ID SKU.
     *
     * @param array $skuData
     * @param string $tableName
     * @return array [key => sku_id, ...]
     */
    private function fetchSkuIds(array $skuData, string $tableName): array
    {
        if (empty($skuData)) {
            return [];
        }
        $conditions = ['or'];
        foreach ($skuData as $item) {
            $conditions[] = [
                'and',
                ['global_product_id' => $item['global_product_id']],
                ['variant_hash' => $item['variant_hash']]
            ];
        }
        $rows = (new Query())
            ->select(['id', 'global_product_id', 'variant_hash'])
            ->from($tableName)
            ->where($conditions)
            ->all();
        $result = [];
        foreach ($rows as $row) {
            $key = $row['global_product_id'] . '|' . $row['variant_hash'];
            $result[$key] = (int)$row['id'];
        }
        return $result;
    }

    /**
     * Предзагрузка существующих SKU.
     *
     * @param array $skuKeys
     * @return array [key => sku_id, ...]
     */
    public function preloadSkus(array $skuKeys): array
    {
        if (empty($skuKeys)) {
            return [];
        }
        $conditions = ['or'];
        foreach (array_keys($skuKeys) as $skuKey) {
            [$gpId, $vHash] = explode('|', $skuKey, 2);
            $conditions[] = [
                'and',
                ['global_product_id' => (int)$gpId],
                ['variant_hash' => $vHash]
            ];
        }
        $rows = (new Query())
            ->select(['id', 'global_product_id', 'variant_hash'])
            ->from(ProductSkus::tableName())
            ->where($conditions)
            ->all();
        $result = [];
        foreach ($rows as $row) {
            $key = $row['global_product_id'] . '|' . $row['variant_hash'];
            $result[$key] = (int)$row['id'];
        }
        return $result;
    }
}