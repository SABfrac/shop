<?php

namespace app\services\catalog;

use app\models\GlobalProducts;
use Yii;
use yii\db\Connection;
use yii\db\Query;
use yii\db\Transaction;
use app\helper\DataNormalizer;

/**
 * Сервис для работы с GlobalProduct.
 */
class GlobalProductService
{

    private DataNormalizerService $dataNormalizerService;

    public function __construct( DataNormalizerService $dataNormalizerService)
    {

        $this->dataNormalizerService = $dataNormalizerService;
    }

    /**
     * Предзагружает существующие GlobalProducts по нескольким критериям.
     *
     * @param array $matchKeys
     * @param array $gtins
     * @param array $modelNumbersByBrand [brandId => [modelNumber => true]]
     * @param array $canonicalNamesByCategory [categoryId => [canonicalName => true]]
     * @return array
     */
    public function preloadGlobalProducts(
        array $matchKeys,
        array $gtins,
        array $modelNumbersByBrand,
        array $canonicalNamesByCategory
    ): array {
        $conditions = ['or'];
        if (!empty($matchKeys)) {
            $conditions[] = ['match_key' => $matchKeys];
        }
        if (!empty($gtins)) {
            $conditions[] = ['gtin' => $gtins];
        }
        if (!empty($modelNumbersByBrand)) {
            foreach ($modelNumbersByBrand as $brandId => $modelNumbers) {
                $conditions[] = [
                    'and',
                    ['brand_id' => $brandId],
                    ['model_number' => array_keys($modelNumbers)]
                ];
            }
        }
        if (!empty($canonicalNamesByCategory)) {
            foreach ($canonicalNamesByCategory as $categoryId => $names) {
                $conditions[] = [
                    'and',
                    ['category_id' => $categoryId],
                    ['canonical_name_normalized' => array_keys($names)]
                ];
            }
        }

        if (count($conditions) <= 1) {
            return [
                'by_gtin' => [],
                'by_model_brand' => [],
                'by_match_key' => [],
                'by_canonical_name_cat' => []
            ];
        }

        $rows = (new Query())
            ->select([
                'id',
                'match_key',
                'gtin',
                'model_number',
                'brand_id',
                'category_id',
                'canonical_name_normalized'
            ])
            ->from(GlobalProducts::tableName())
            ->where($conditions)
            ->all();



        return $this->groupGlobalProducts($rows);
    }

    /**
     * Группирует загруженные строки GP по ключам поиска.
     *
     * @param array $rows
     * @return array
     */
    private function groupGlobalProducts(array $rows): array
    {
        $result = [
            'by_gtin' => [],
            'by_model_brand' => [],
            'by_match_key' => [],
            'by_canonical_name_cat' => []
        ];
        foreach ($rows as $row) {
            if (!empty($row['gtin'])) {
                $result['by_gtin'][$row['gtin']] = $row;
            }
            if (!empty($row['model_number']) && !empty($row['brand_id'])) {
                $result['by_model_brand'][$row['brand_id']][$row['model_number']] = $row;
            }
            if (!empty($row['match_key'])) {
                $result['by_match_key'][$row['match_key']] = $row;
            }
            if (!empty($row['canonical_name_normalized']) && !empty($row['category_id'])) {
                $key = $row['category_id'] . '|' . $row['canonical_name_normalized'];
                $result['by_canonical_name_cat'][$key] = $row;
            }
        }
        return $result;
    }

    /**
     * Bulk insert для GlobalProducts (используется в bulk-импорте).
     *
     * @param array $uniqueData
     * @return array [match_key => gp_id, ...]
     */
    public function bulkInsertGlobalProducts(array $uniqueData): array
    {
        if (empty($uniqueData)) {
            return [];
        }

        $chunkSize = 1000;
        $allMatchKeys = array_column($uniqueData, 'match_key');
        $result = [];

        foreach (array_chunk($uniqueData, $chunkSize) as $chunk) {
            $this->insertGlobalProductsChunk($chunk);
        }
        $result = $this->fetchGlobalProductIds( $allMatchKeys, GlobalProducts::tableName());

        Yii::info([
            'action' => 'bulkInsertGlobalProducts',
            'unique' => count($uniqueData),
            'output' => count($result),
        ], 'performance');

        return $result;
    }


    private function insertGlobalProductsChunk(array $chunk): void
    {
        if (empty($chunk)) {
            return;
        }

        $db = Yii::$app->db;
        $gpTable = GlobalProducts::tableName();

        $placeholders = [];
        $params = [];
        foreach ($chunk as $idx => $data) {
            $placeholders[] = sprintf(
                '(:cn%d, :cnn%d, :bid%d, :cid%d, :gtin%d, :mn%d, :mk%d)',
                $idx, $idx, $idx, $idx, $idx, $idx, $idx
            );
            $params[":cn{$idx}"] = $data['canonical_name'];
            $params[":cnn{$idx}"] = $data['canonical_name_normalized'] ?? '';
            $params[":bid{$idx}"] = $data['brand_id'];
            $params[":cid{$idx}"] = $data['category_id'];
            $params[":gtin{$idx}"] = $data['gtin'];
            $params[":mn{$idx}"] = $data['model_number'];
            $params[":mk{$idx}"] = $data['match_key'];
        }

        $sql = "
        INSERT INTO {$gpTable} (
            canonical_name,
            canonical_name_normalized,
            brand_id,
            category_id,
            gtin,
            model_number,
            match_key
        )
        VALUES " . implode(', ', $placeholders) . "
        ON CONFLICT (match_key) DO NOTHING
    ";

        $db->createCommand($sql, $params)->execute();
    }

    /**
     * Получает ID GP по match_key.
     *
     * @param array $matchKeys
     * @param string $tableName
     * @return array [match_key => gp_id, ...]
     */
    private function fetchGlobalProductIds(array $matchKeys, string $tableName): array
    {
        if (empty($matchKeys)) {
            return [];
        }
        $rows = (new Query())
            ->select(['id', 'match_key'])
            ->from($tableName)
            ->where(['match_key' => $matchKeys])
            ->all();
        $result = [];
        foreach ($rows as $row) {
            $result[$row['match_key']] = (int)$row['id'];
        }
        return $result;
    }
}