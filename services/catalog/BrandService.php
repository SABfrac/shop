<?php

namespace app\services\catalog;


use app\models\Brands;
use Yii;
use yii\db\Connection;
use yii\db\Query;

/**
 * Сервис для работы с брендами.
 */
class BrandService
{


    /**
     * Обеспечивает существование брендов по их ключам.
     * Ищет существующие, создает отсутствующие.
     *
     * @param array $brandKeys Массив уникальных ключей брендов (например, нормализованные имена).
     * @return array Ассоциативный массив ['brand_key' => BrandModel, ...].
     */
    public function ensureBrands(array $brandKeys): array
    {
        if (empty($brandKeys)) {
            return [];
        }

        // Убираем дубликаты и пустые
        $brandKeys = array_filter(array_unique($brandKeys));
        if (empty($brandKeys)) {
            return [];
        }

        $tableName = Brands::tableName();
        $db = Yii::$app->db;

        // Шаг 1: вставка новых (без возврата)

        // Вставка новых
        $placeholders = [];
        $params = [];
        foreach ($brandKeys as $idx => $brandKey) {
            $placeholders[] = "(:n{$idx})";
            $params[":n{$idx}"] = $brandKey;
        }
            $sql = "
            INSERT INTO {$tableName} (name)
            VALUES " . implode(', ', $placeholders) . "
            ON CONFLICT (name) DO NOTHING
        ";
            $db->createCommand($sql, $params)->execute();


        // Шаг 2: всегда возвращаем актуальные записи
        $rows =  (new Query())
            ->select(['name', 'id'])
            ->from($tableName)
            ->where(['name' => array_values($brandKeys)])
            ->all($db);

        return array_column($rows, 'id', 'key' );

    }

    /**
     * Находит или создает бренд по имени.
     *
     * @param string $name Имя бренда.
     * @return Brands|null
     */
    public function findOrCreate(string $name): ?Brands
    {
        $brand = Brands::find()->where(['name' => $name])->one();
        if (!$brand) {
            $brand = new Brands();
            $brand->name = $name;
            if (!$brand->save()) {
                throw new \Exception('Failed to create brand: ' . json_encode($brand->errors));
            }
        }
        return $brand;
    }
}