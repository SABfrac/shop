<?php

namespace app\services\catalog;

use app\models\Offers;
use Yii;
use yii\db\Connection;
use yii\db\Query;
use app\controllers\OffersController;

/**
 * Сервис для работы с Offers.
 */
class OfferService
{

    /**
     * Upsert (INSERT ... ON CONFLICT ... DO UPDATE) для офферов (используется в bulk-импорте).
     *
     * @param array $offers
     * @param int $vendorId
     * @return array [offer_id, ...]
     */
    public function upsertOffers(array $offers, int $vendorId): array
    {
        if (empty($offers)) {
            return [];
        }

        $deduplicated = $this->deduplicateOffers($offers);
        $this->validateOffersStructure($deduplicated);

        $chunkSize = 1000;
        $allResults = [];

        foreach (array_chunk($deduplicated, $chunkSize) as $chunk) {
            $chunkIds = $this->upsertOffersChunk($chunk);
            $allResults = array_merge($allResults, $chunkIds);
        }

        return $allResults;
    }


    private function upsertOffersChunk(array $offersChunk): array
    {
        if (empty($offersChunk)) {
            return [];
        }

        $db = Yii::$app->db;
        $tableName = Offers::tableName();

        $valuePlaceholders = [];
        $params = [];
        foreach ($offersChunk as $index => $offer) {
            $valuePlaceholders[] = sprintf(
                '(:vs%d, :si%d, :vi%d, :pr%d, :st%d, :wr%d, :cn%d, :ss%d, :so%d)',
                $index, $index, $index, $index, $index, $index, $index, $index, $index
            );
            $params[":vs{$index}"] = $offer['vendor_sku'] ?? OffersController::generate();
            $params[":si{$index}"] = $offer['sku_id'];
            $params[":vi{$index}"] = $offer['vendor_id'];
            $params[":pr{$index}"] = $offer['price'];
            $params[":st{$index}"] = $offer['stock'];
            $params[":wr{$index}"] = $offer['warranty'] ?? null;
            $params[":cn{$index}"] = $offer['condition'];
            $params[":ss{$index}"] = $offer['status'];
            $params[":so{$index}"] = $offer['sort_order'];
        }

        $columns = 'vendor_sku, sku_id, vendor_id, price, stock, warranty, condition, status, sort_order';
        $updateSet = implode(', ', [
            'vendor_sku = EXCLUDED.vendor_sku',
            'price = EXCLUDED.price',
            'stock = EXCLUDED.stock',
            'warranty = EXCLUDED.warranty',
            'condition = EXCLUDED.condition',
            'status = EXCLUDED.status',
            'sort_order = EXCLUDED.sort_order',
            'updated_at = NOW()'
        ]);

        $sql = "
        INSERT INTO {$tableName} ({$columns})
        VALUES " . implode(', ', $valuePlaceholders) . "
        ON CONFLICT (vendor_id, sku_id) DO UPDATE SET {$updateSet}
        RETURNING id, vendor_id, sku_id
    ";

        $rows = $db->createCommand($sql, $params)->queryAll();

        // Сопоставляем возвращённые ID с входящими офферами
        $resultMap = [];
        foreach ($rows as $row) {
            $key = $row['vendor_id'] . '|' . $row['sku_id'];
            $resultMap[$key] = (int)$row['id'];
        }

        $result = [];
        foreach ($offersChunk as $offer) {
            $key = $offer['vendor_id'] . '|' . $offer['sku_id'];
            $result[] = $resultMap[$key] ?? null;
        }

        return $result;
    }

    /**
     * Дедупликация офферов.
     *
     * @param array $offers
     * @return array
     */
    private function deduplicateOffers(array $offers): array
    {
        $unique = [];
        $seen = [];
        foreach ($offers as $offer) {
            $key = $offer['vendor_id'] . '|' . $offer['sku_id'];
            if (isset($seen[$key])) {
                continue;
            }
            $unique[] = $offer;
            $seen[$key] = true;
        }
        return $unique;
    }

    /**
     * Валидация структуры офферов.
     *
     * @param array $offers
     * @throws \InvalidArgumentException
     */
    private function validateOffersStructure(array $offers): void
    {
        $required = ['vendor_sku', 'sku_id', 'vendor_id', 'price', 'stock', 'condition', 'status', 'sort_order'];
        foreach ($offers as $i => $offer) {
            foreach ($required as $field) {
                if (!isset($offer[$field])) {
                    throw new \InvalidArgumentException("Offer #{$i} missing field: {$field}");
                }
            }
        }
    }
}