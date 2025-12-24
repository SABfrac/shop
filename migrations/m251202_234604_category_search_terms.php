<?php

use yii\db\Migration;

class m251202_234604_category_search_terms extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%category_search_terms}}', [
            'category_id' => $this->integer()->notNull(),
            'term' => $this->string(50)->notNull(),
        ]);

        $this->addPrimaryKey('pk_category_search_terms', '{{%category_search_terms}}', ['category_id', 'term']);
        $this->createIndex('idx_category_search_terms_category_id', '{{%category_search_terms}}', 'category_id');

        // Сопоставление категорий → термины
        $termsMap = $this->buildTermsMap();

        // Вставляем все термины
        $rows = [];
        foreach ($termsMap as $categoryId => $terms) {
            foreach ($terms as $term) {
                $rows[] = [$categoryId, $term];
            }
        }

        if (!empty($rows)) {
            $this->batchInsert('{{%category_search_terms}}', ['category_id', 'term'], $rows);
        }


    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%category_search_terms}}');
    }

    private function buildTermsMap(): array
    {
        $map = [];

        // === ЭЛЕКТРОНИКА (parent_id = 1) ===
        $map[11] = ['phone', 'телефон', 'смартфон', 'smartphone', 'mobile', 'айфон', 'iphone'];
        $map[12] = ['ноутбук', 'laptop', 'notebook', 'компьютер', 'computer'];
        $map[15] = ['телевизор', 'tv', 'television', 'дисплей', 'display'];
        $map[16] = ['фотоаппарат', 'камера', 'camera', 'photo', 'фототехника'];
        $map[17] = ['аудиотехника', 'audio', 'наушники', 'колонки', 'headphones', 'speakers'];
        $map[18] = ['приставка', 'консоль', 'game console', 'playstation', 'xbox'];
        $map[19] = ['процессор', 'cpu', 'processor'];
        $map[20] = ['видеокарта', 'gpu', 'graphics card'];
        $map[21] = ['материнская плата', 'motherboard'];
        $map[22] = ['оперативная память', 'ram', 'память'];
        $map[23] = ['накопитель', 'ssd', 'hdd', 'диск'];
        $map[24] = ['блок питания', 'power supply'];
        $map[25] = ['корпус', 'case'];
        $map[26] = ['охлаждение', 'cooler', 'кулер', 'вентилятор'];
        $map[27] = ['монитор', 'display', 'screen'];
        $map[28] = ['клавиатура', 'keyboard'];
        $map[29] = ['мышь', 'mouse'];
        $map[30] = ['комплект', 'keyboard mouse', 'клавиатура мышь'];
        $map[31] = ['игровой набор', 'gaming set'];
        $map[32] = ['коврик', 'mouse pad'];
        $map[33] = ['микрофон', 'microphone'];
        $map[34] = ['наушники', 'гарнитура', 'headphones', 'headset'];
        $map[35] = ['колонки', 'speakers'];
        $map[36] = ['планшет', 'графический планшет', 'tablet'];
        $map[37] = ['внешний диск', 'external drive'];
        $map[38] = ['веб-камера', 'webcam'];
        $map[39] = ['док-станция', 'dock'];
        $map[40] = ['кабель', 'адаптер', 'cable', 'adapter'];

        // === ОДЕЖДА ===
        $clothingTerms = ['одежда', 'clothing'];
        $menTerms = ['мужская одежда', 'menswear', 'мужское'];
        $womenTerms = ['женская одежда', 'womenswear', 'женское'];

        // Мужская одежда (parent_id = 41)
        $menCategoryIds = [46, 47, 48, 49, 50, 51, 52];
        foreach ($menCategoryIds as $id) {
            $map[$id] = array_unique(array_merge($clothingTerms, $menTerms));
        }

        // Женская одежда (parent_id = 42)
        $womenCategoryIds = [54, 55, 56, 57, 58, 59, 60, 61];
        foreach ($womenCategoryIds as $id) {
            $map[$id] = array_unique(array_merge($clothingTerms, $womenTerms));
        }

        // Детская одежда (parent_id = 43)
        $kidsClothingIds = [77, 78, 79, 80];
        foreach ($kidsClothingIds as $id) {
            $map[$id] = ['детская одежда', 'kids clothing', 'детское'];
        }

        // === ОБУВЬ (parent_id = 44) ===
        $shoeTerms = ['обувь', 'shoes', 'footwear'];
        $shoeIds = [81, 82, 83, 84, 85];
        foreach ($shoeIds as $id) {
            $map[$id] = $shoeTerms;
        }

        // === АКСЕССУАРЫ (parent_id = 45) ===
        $accessoryTerms = ['аксессуары', 'accessories'];
        $accessoryIds = [86, 87, 88, 89, 90];
        foreach ($accessoryIds as $id) {
            $map[$id] = $accessoryTerms;
        }

        // === БЕЛЬЁ И НОСКИ (parent_id = 53, 62) ===
        $underwearIds = [63, 64, 65, 66, 67, 68, 69, 70, 71, 72, 73, 74, 75, 76];
        foreach ($underwearIds as $id) {
            $map[$id] = ['бельё', 'underwear', 'нижнее бельё', 'swimwear', 'купальник'];
        }

        // === МЕБЕЛЬ И ТОВАРЫ ДЛЯ ДОМА (parent_id = 4) ===
        $homeIds = [120, 121, 122, 123, 124, 125];
        foreach ($homeIds as $id) {
            $map[$id] = ['дом', 'home', 'товары для дома', 'household'];
        }

        // === КРАСОТА И ЗДОРОВЬЕ (parent_id = 5) ===
        $beautyIds = [126, 127, 128, 129, 130, 131];
        foreach ($beautyIds as $id) {
            $map[$id] = ['красота', 'здоровье', 'beauty', 'health', 'косметика', 'cosmetics'];
        }

        // === СПОРТ И ОТДЫХ (parent_id = 6) ===
        $sportIds = [132, 133, 134, 135, 136, 137];
        foreach ($sportIds as $id) {
            $map[$id] = ['спорт', 'fitness', 'отдых', 'sports', 'outdoor', 'туризм', 'рыбалка'];
        }

        // === ДЕТСКИЕ ТОВАРЫ (parent_id = 7) ===
        $kidsIds = [138, 139, 140, 141, 142, 143];
        foreach ($kidsIds as $id) {
            $map[$id] = ['детские товары', 'kids', 'children', 'baby'];
        }

        // === ПРОДУКТЫ (parent_id = 8) ===
        $foodIds = [144, 145, 146, 147, 148, 149];
        foreach ($foodIds as $id) {
            $map[$id] = ['еда', 'продукты', 'food', 'groceries'];
        }

        // === БЫТОВАЯ ХИМИЯ (parent_id = 9) ===
        $chemicalIds = [150, 151, 152, 153, 154, 155];
        foreach ($chemicalIds as $id) {
            $map[$id] = ['химия', 'cleaning', 'бытовая химия', 'моющие средства'];
        }

        // === АВТОТОВАРЫ (parent_id = 10) ===
        $autoIds = [157, 158, 159, 160];
        foreach ($autoIds as $id) {
            $map[$id] = ['авто', 'car', 'автотовары', 'автохимия', 'автоаксессуары'];
        }

        // === АВТОЗАПЧАСТИ (parent_id = 161–170) ===
        $autoPartIds = range(171, 248); // все ID от 171 до 248
        foreach ($autoPartIds as $id) {
            $map[$id] = ['автозапчасти', 'auto parts', 'запчасти'];
        }

        // === БЫТОВАЯ ТЕХНИКА (parent_id = 3, 91, 94, 108) ===
        $applianceIds = [92, 93, 95, 96, 97, 98, 99, 100, 101, 102, 103, 104, 105, 106, 107, 109, 110, 111, 112, 113, 114, 115, 116, 117, 118, 119];
        foreach ($applianceIds as $id) {
            $map[$id] = ['техника', 'appliance', 'бытовая техника', 'home appliance'];
        }

        return $map;
    }
}
