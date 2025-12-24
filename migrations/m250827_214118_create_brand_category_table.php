<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%brand_category}}`.
 */
class m250827_214118_create_brand_category_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Таблица связей "бренд — категория"
        $this->createTable('{{%brand_category}}', [
            'brand_id'    => $this->integer()->notNull(),
            'category_id' => $this->integer()->notNull(),
        ]);

        // Составной первичный ключ
        $this->addPrimaryKey('pk-brand_category', '{{%brand_category}}', ['brand_id', 'category_id']);

        // Индексы
        $this->createIndex('idx-brand_category-brand_id', '{{%brand_category}}', 'brand_id');
        $this->createIndex('idx-brand_category-category_id', '{{%brand_category}}', 'category_id');
// Мэппинг бренд -> набор id категорий (в основном листовых)
        $map = [
            // Электроника
            1  => [11, 12, 27, 34], // Apple: смартфоны, ноутбуки, мониторы, гарнитуры
            2  => [11, 15, 17, 27, 97, 99, 100, 101, 105, 112, 113, 114, 115, 116, 117], // Samsung
            3  => [11, 15, 17, 27, 34, 35], // Xiaomi
            4  => [11, 12, 17, 27, 34], // Huawei
            5  => [11, 15, 16, 17, 18, 27, 33, 34, 35], // Sony

            // Одежда/обувь
            6  => [47, 51, 52, 69, 81, 84, 56, 60], // Nike
            7  => [47, 51, 52, 69, 81, 84, 56, 60], // Adidas
            8  => [46, 47, 48, 49, 50, 52, 54, 55, 56, 57, 58, 59, 61, 77, 78, 82], // Zara
            9  => [47, 51, 52, 56, 60, 61, 69, 77, 78, 82, 83], // H&M
            10 => [47, 51, 60, 84, 69, 81], // Reebok

            // Бытовая техника
            11 => [97, 98, 99, 100, 101, 102, 103, 112, 113, 114, 115, 116, 117, 118, 119, 124], // Bosch (+ инструменты)
            12 => [15, 27, 17, 97, 99, 100, 101, 105, 112, 113, 115, 116, 117], // LG
            13 => [15, 27, 17, 92, 96, 107, 111], // Philips
            14 => [97, 99, 100, 101, 109, 112, 113, 114, 115, 116, 117, 118, 119], // Indesit
            15 => [97, 99, 100, 101, 105, 112, 113, 114, 115, 116, 117, 118, 119], // Whirlpool

            // Красота и здоровье
            16 => [126, 127, 128], // L'Oreal
            17 => [126, 129], // Nivea
            18 => [126], // Maybelline
            19 => [126, 128], // Garnier
            20 => [129, 126], // Dove
        ];

        // Автобренды -> типовые листовые подкатегории автозапчастей/ТО/охлаждения/электрики и т. д.
        $autoLeafs = [
            // Трансмиссия и сцепление
            180, 181,
            // Подвеска и рулевое
            187, 188, 189, 190, 191, 192, 193, 194,
            // Тормоза
            195, 196, 197, 198, 199, 200, 201,
            // Электрика
            202, 203, 204, 205, 206, 207, 208, 209,
            // Охлаждение
            226, 227, 228, 229, 230, 231, 232,
            // Фильтры и жидкости
            233, 234, 235, 236, 237, 238, 239, 240,
            // ТО
            241, 242, 243, 244, 245, 246, 247, 248,
        ];
        foreach ([21, 22, 23, 24, 25] as $brandId) { // Toyota, Ford, BMW, Mercedes-Benz, Audi
            $map[$brandId] = $autoLeafs;
        }

        // Подготовка данных для batchInsert
        $rows = [];
        foreach ($map as $brandId => $catIds) {
            foreach (array_unique($catIds) as $categoryId) {
                $rows[] = [$brandId, $categoryId];
            }
        }

        if (!empty($rows)) {
            $this->batchInsert('{{%brand_category}}', ['brand_id', 'category_id'], $rows);
        }

        $this->addForeignKey(
            'fk-brand_category-brand_id',
            '{{%brand_category}}',
            'brand_id',
            '{{%brands}}',
            'id',
            'CASCADE',
            'CASCADE'
        );


    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%brand_category}}');
    }
}
