<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%attribute_options}}`.
 */
class m250530_145827_create_attribute_options_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%attribute_options}}', [
            'id' => $this->primaryKey(),
            'attribute_id' => $this->integer()->notNull(),
            'value' => $this->string(255)->notNull(),

            // Дополнительные поля (опционально)
            'slug' => $this->string(255)->unique(),
            'sort_order' => $this->integer()->defaultValue(0),

            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);

        // Добавляем внешний ключ
        $this->addForeignKey(
            'fk-attribute_options-attribute_id',
            '{{%attribute_options}}',
            'attribute_id',
            '{{%attributes}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        // Индекс для ускорения поиска
        $this->createIndex(
            'idx-attribute_options-attribute_id',
            '{{%attribute_options}}',
            'attribute_id'
        );

        $this->createIndex('idx-product_attribute_values-attribute_id',
            'product_attribute_values',
            'attribute_id');

        $this->batchInsert('{{%attribute_options}}',
            ['attribute_id', 'value', 'slug', 'sort_order'],
            [
                // Общие атрибуты
                [1, 'Название товара', 'product-name', 1], // Для примера, обычно название не из справочника

                // Бренды (атрибут "Бренд" - предположим ID=3)
                [3, 'Apple', 'apple', 1],
                [3, 'Samsung', 'samsung', 2],
                [3, 'Xiaomi', 'xiaomi', 3],
                [3, 'Bosch', 'bosch', 4],
                [3, 'Nike', 'nike', 5],

                // Для атрибута "Страна производитель" (ID=4)
                [4, 'Россия', 'russia', 1],
                [4, 'Китай', 'china', 2],
                [4, 'Германия', 'germany', 3],
                [4, 'США', 'usa', 4],
                [4, 'Япония', 'japan', 5],
                [4, 'Южная Корея', 'south-korea', 6],
                [4, 'Италия', 'italy', 7],
                [4, 'Франция', 'france', 8],
                [4, 'Турция', 'turkey', 9],
                [4, 'Испания', 'spain', 10],
                [4, 'Беларусь', 'belarus', 11],
                [4, 'Тайвань', 'taiwan', 12],
                [4, 'Чехия', 'czech', 13],

                // Цвета (атрибут "Цвет" - предположим ID=7)
                [7, 'Черный', 'black', 1],
                [7, 'Белый', 'white', 2],
                [7, 'Красный', 'red', 3],
                [7, 'Синий', 'blue', 4],
                [7, 'Зеленый', 'green', 5],
                [7, 'Серый', 'gray', 6],
                [7, 'Желтый', 'yellow', 7],
                [7, 'Розовый', 'pink', 8],

                // Размеры РФ (атрибут "Размер (РФ)" - ID=8)
                [8, '40', 'ru-36', 1],
                [8, '42', 'ru-38', 2],
                [8, '40', 'ru-40', 3],
                [8, '42', 'ru-42', 4],
                [8, '44', 'ru-44', 5],
                [8, '46', 'ru-46', 6],
                [8, '48', 'ru-48', 7],
                [8, '50', 'ru-50', 8],
                [8, '52', 'ru-52', 9],
                [8, '54', 'ru-54', 10],


                // Размеры EU (ID=9) - одежда
                [9, 'XXS', 'eu-xxs', 1],
                [9, 'XS', 'eu-xs', 2],
                [9, 'S', 'eu-s', 3],
                [9, 'M', 'eu-m', 4],
                [9, 'L', 'eu-l', 5],
                [9, 'XL', 'eu-xl', 6],
                [9, 'XXL', 'eu-xxl', 7],
                [9, 'XXXL', 'eu-xxxl', 8],

                // Размеры US (ID=10) - одежда
                [10, '32', 'us-32', 1],
                [10, '34', 'us-34', 2],
                [10, '36', 'us-36', 3],
                [10, '38', 'us-38', 4],
                [10, '40', 'us-40', 5],
                [10, '42', 'us-42', 6],
                [10, '44', 'us-44', 7],

                // Материалы (ID=11)
                [11, 'Хлопок', 'cotton', 1],
                [11, 'Полиэстер', 'polyester', 2],
                [11, 'Шерсть', 'wool', 3],
                [11, 'Кожа', 'leather', 4],
                [11, 'Джинс', 'denim', 5],
                [11, 'Лен', 'linen', 6],

                // Сезон (ID=12)
                [12, 'Лето', 'summer', 1],
                [12, 'Зима', 'winter', 2],
                [12, 'Демисезон', 'all-season', 3],
                [12, 'Круглогодичный', 'year-round', 4],

                // Пол (атрибут "Пол" - ID=13)
                [13, 'Мужской', 'male', 1],
                [13, 'Женский', 'female', 2],
                [13, 'Унисекс', 'unisex', 3],
                [13, 'Детский', 'kids', 4],

                //модель процессора
                [14, 'Intel', 'intel', 1],
                [14, 'AMD', 'amd', 2],
                //процессор
                [15, 'Intel Core i3', 'intel-core-i3', 1],
                [15, 'Intel Core i5', 'intel-core-i5', 2],
                [15, 'Intel Core i7', 'intel-core-i7', 3],
                [15, 'AMD Ryzen 3', 'amd-ryzen-3', 4],
                [15, 'AMD Ryzen 5', 'amd-ryzen-5', 5],
                [15, 'AMD Ryzen 7', 'amd-ryzen-7', 6],
                [15, 'AMD Ryzen 9', 'amd-ryzen-9', 7],
                [15, 'Apple A8', 'apple-a8', 8],
                [15, 'Apple A8X', 'apple-a8x', 9],
                [15, 'Apple A9', 'apple-a9', 10],
                [15, 'Apple A9X', 'apple-a9x', 11],
                [15, 'Apple A10 Fusion', 'apple-a10-fusion', 12],
                [15, 'Apple A10X Fusion', 'apple-a10x-fusion', 13],
                [15, 'Apple A11 Bionic', 'apple-a11-bionic', 14],
                [15, 'Apple A12 Bionic', 'apple-a12-bionic', 15],
                [15, 'Apple A12X Bionic', 'apple-a12x-bionic', 16],
                [15, 'Apple A12Z Bionic', 'apple-a12z-bionic', 17],
                [15, 'Apple A13 Bionic', 'apple-a13-bionic', 18],
                [15, 'Apple A14 Bionic', 'apple-a14-bionic', 19],
                [15, 'Apple A15 Bionic', 'apple-a15-bionic', 20],
                [15, 'Apple A16 Bionic', 'apple-a16-bionic', 21],
                [15, 'Apple A17 Pro', 'apple-a17-pro', 22],
                [15, 'Apple M1', 'apple-m1', 23],
                [15, 'Apple M1 Pro', 'apple-m1-pro', 24],
                [15, 'Apple M1 Max', 'apple-m1-max', 25],
                [15, 'Apple M1 Ultra', 'apple-m1-ultra', 26],
                [15, 'Apple M2', 'apple-m2', 27],
                [15, 'Apple M2 Pro', 'apple-m2-pro', 28],
                [15, 'Apple M2 Max', 'apple-m2-max', 29],
                [15, 'Apple M2 Ultra', 'apple-m2-ultra', 30],
                [15, 'Apple M3', 'apple-m3', 31],
                [15, 'Apple M3 Pro', 'apple-m3-pro', 32],
                [15, 'Apple M3 Max', 'apple-m3-max', 33],
                [15, 'Apple M4 ', 'apple-m4', 34],
                // Snapdragon
                [15, 'Snapdragon 200', 'snapdragon-200', 35],
                [15, 'Snapdragon 400', 'snapdragon-400', 36],
                [15, 'Snapdragon 600', 'snapdragon-600', 37],
                [15, 'Snapdragon 800', 'snapdragon-800', 38],
                [15, 'Snapdragon 810', 'snapdragon-810', 39],
                [15, 'Snapdragon 820', 'snapdragon-820', 40],
                [15, 'Snapdragon 835', 'snapdragon-835', 41],
                [15, 'Snapdragon 845', 'snapdragon-845', 42],
                [15, 'Snapdragon 855', 'snapdragon-855', 43],
                [15, 'Snapdragon 865', 'snapdragon-865', 44],
                [15, 'Snapdragon 870', 'snapdragon-870', 45],
                [15, 'Snapdragon 888', 'snapdragon-888', 46],
                [15, 'Snapdragon 8 Gen 1', 'snapdragon-8-gen1', 47],
                [15, 'Snapdragon 8+ Gen 1', 'snapdragon-8-plus-gen1', 48],
                [15, 'Snapdragon 8 Gen 2', 'snapdragon-8-gen2', 49],
                [15, 'Snapdragon 8 Gen 3', 'snapdragon-8-gen3', 50],
                // MediaTek
                [15, 'MediaTek Helio P10', 'mediatek-helio-p10', 51],
                [15, 'MediaTek Helio P20', 'mediatek-helio-p20', 52],
                [15, 'MediaTek Helio P22', 'mediatek-helio-p22', 53],
                [15, 'MediaTek Helio P60', 'mediatek-helio-p60', 54],
                [15, 'MediaTek Helio P70', 'mediatek-helio-p70', 55],
                [15, 'MediaTek Helio G80', 'mediatek-helio-g80', 56],
                [15, 'MediaTek Helio G90', 'mediatek-helio-g90', 57],
                [15, 'MediaTek Helio G95', 'mediatek-helio-g95', 58],
                [15, 'MediaTek Dimensity 700', 'mediatek-dimensity-700', 59],
                [15, 'MediaTek Dimensity 720', 'mediatek-dimensity-720', 60],
                [15, 'MediaTek Dimensity 800', 'mediatek-dimensity-800', 61],
                [15, 'MediaTek Dimensity 1000', 'mediatek-dimensity-1000', 62],
                [15, 'MediaTek Dimensity 1100', 'mediatek-dimensity-1100', 63],
                [15, 'MediaTek Dimensity 1200', 'mediatek-dimensity-1200', 64],
                [15, 'MediaTek Dimensity 9000', 'mediatek-dimensity-9000', 65],
                [15, 'MediaTek Dimensity 9200', 'mediatek-dimensity-9200', 66],
                [15, 'MediaTek Dimensity 9300', 'mediatek-dimensity-9300', 67],

                [15, 'Samsung Exynos 4412', 'samsung-exynos-4412', 68],
                [15, 'Samsung Exynos 5250', 'samsung-exynos-5250', 69],
                [15, 'Samsung Exynos 5410', 'samsung-exynos-5410', 70],
                [15, 'Samsung Exynos 5420', 'samsung-exynos-5420', 71],
                [15, 'Samsung Exynos 5430', 'samsung-exynos-5430', 72],
                [15, 'Samsung Exynos 7420', 'samsung-exynos-7420', 73],
                [15, 'Samsung Exynos 7870', 'samsung-exynos-7870', 74],
                [15, 'Samsung Exynos 8890', 'samsung-exynos-8890', 75],
                [15, 'Samsung Exynos 8895', 'samsung-exynos-8895', 76],
                [15, 'Samsung Exynos 9810', 'samsung-exynos-9810', 77],
                [15, 'Samsung Exynos 9820', 'samsung-exynos-9820', 78],
                [15, 'Samsung Exynos 990', 'samsung-exynos-990', 79],
                [15, 'Samsung Exynos 2100', 'samsung-exynos-2100', 80],
                [15, 'Samsung Exynos 2200', 'samsung-exynos-2200', 81],

                // Huawei Kirin
                [15, 'Huawei Kirin 650', 'huawei-kirin-650', 82],
                [15, 'Huawei Kirin 655', 'huawei-kirin-655', 83],
                [15, 'Huawei Kirin 659', 'huawei-kirin-659', 84],
                [15, 'Huawei Kirin 710', 'huawei-kirin-710', 85],
                [15, 'Huawei Kirin 810', 'huawei-kirin-810', 86],
                [15, 'Huawei Kirin 820', 'huawei-kirin-820', 87],
                [15, 'Huawei Kirin 9000', 'huawei-kirin-9000', 88],
                [15, 'Huawei Kirin 9000E', 'huawei-kirin-9000e', 89],
                [15, 'Huawei Kirin 9000S', 'huawei-kirin-9000s', 90],
                [15, 'Huawei Kirin 9010', 'huawei-kirin-9010', 91],

                // Дополнительные современные процессоры (2023-2024)
                [15, 'Qualcomm Snapdragon 7 Gen 3', 'snapdragon-7-gen3', 92],
                [15, 'MediaTek Dimensity 8300', 'mediatek-dimensity-8300', 93],
                [15, 'Google Tensor G3', 'google-tensor-g3', 94],
                [15, 'Google Tensor G2', 'google-tensor-g2', 95],


                //поддерживаемые типы RAM
                [48, 'DDR', 'ddr', 1],
                [48, 'DDR2', 'ddr-2', 2],
                [48, 'DDR3', 'ddr-3', 3],
                [48, 'DDR4', 'ddr-4', 4],
                [48, 'DDR5', 'ddr-5', 5],
                [48, 'DDR6', 'ddr-6', 6],


                // орперативная память (гб)
                [16, '2', 'memory-2', 1],
                [16, '4', 'memory-4', 2],
                [16, '8', 'memory-8', 4],
                [16, '16', 'memory-16', 5],
                [16, '32', 'memory-32', 6],
                [16, '64', 'memory-64', 7],

                //встроенная память (гб)
                [17, '4', '4', 1],
                [17, '8', '8', 2],
                [17, '16', '16', 3],
                [17, '32', '32', 4],
                [17, '64', '64', 5],
                [17, '128', '128', 6],
                [17, '256', '256', 7],
                [17, '512', '512', 8],
                [17, '1024', '1024', 9],

                //диагональ экрана (дюймы)

                [19, '13', '13-diagonal', 1],
                [19, '15', '15-diagonal', 2],
                [19, '17', '17-diagonal', 3],
                [19, '24', '24-diagonal', 4],
                [19, '27', '27-diagonal', 5],
                [19, '32', '32-diagonal', 6],

                // Смартфоны (6.1" – 7.9")
                [19, '6.1', '6_1-diagonal', 7],
                [19, '6.3', '6_3-diagonal', 8],
                [19, '6.4', '6_4-diagonal', 9],
                [19, '6.7', '6_7-diagonal', 10],
                [19, '6.9', '6_9-diagonal', 11],
                [19, '7.1', '7_1-diagonal', 12],
                [19, '7.4', '7_4-diagonal', 13],
                [19, '7.7', '7_7-diagonal', 14],
                [19, '7.9', '7_9-diagonal', 15],

                // Планшеты (8.0" – 12.9")
                [19, '8.0', '8_0-diagonal', 16],
                [19, '8.3', '8_3-diagonal', 17],
                [19, '8.7', '8_7-diagonal', 18],
                [19, '9.7', '9_7-diagonal', 19],
                [19, '10.1', '10_1-diagonal', 20],
                [19, '10.5', '10_5-diagonal', 21],
                [19, '10.9', '10_9-diagonal', 22],
                [19, '11.0', '11_0-diagonal', 23],
                [19, '12.4', '12_4-diagonal', 24],
                [19, '12.9', '12_9-diagonal', 25],

                // Телевизоры и мониторы
                [19, '43', '43-diagonal', 26],
                [19, '50', '50-diagonal', 27],
                [19, '55', '55-diagonal', 28],
                [19, '65', '65-diagonal', 29],
                [19, '75', '75-diagonal', 30],
                [19, '85', '85-diagonal', 31],



                // Операционные системы (ID=18)
                [18, 'Android', 'android', 1],
                [18, 'iOS', 'ios', 2],
                [18, 'Windows', 'windows', 3],
                [18, 'macOS', 'macos', 4],
                [18, 'Linux', 'linux', 5],

                // Производители авто (ID=23)

                // Немецкие производители
                [23, 'Audi', 'audi', 1],
                [23, 'BMW', 'bmw', 2],
                [23, 'Mercedes-Benz', 'mercedes', 3],
                [23, 'Volkswagen', 'volkswagen', 4],
                [23, 'Porsche', 'porsche', 5],
                [23, 'Opel', 'opel', 6],

                // Японские производители
                [23, 'Toyota', 'toyota', 7],
                [23, 'Honda', 'honda', 8],
                [23, 'Nissan', 'nissan', 9],
                [23, 'Mazda', 'mazda', 10],
                [23, 'Subaru', 'subaru', 11],
                [23, 'Mitsubishi', 'mitsubishi', 12],
                [23, 'Lexus', 'lexus', 13],
                [23, 'Infiniti', 'infiniti', 14],
                [23, 'Suzuki', 'suzuki', 15],

                // Американские производители
                [23, 'Ford', 'ford', 16],
                [23, 'Chevrolet', 'chevrolet', 17],
                [23, 'Jeep', 'jeep', 18],
                [23, 'Tesla', 'tesla', 19],
                [23, 'Dodge', 'dodge', 20],
                [23, 'Chrysler', 'chrysler', 21],
                [23, 'Cadillac', 'cadillac', 22],

                // Корейские производители
                [23, 'Hyundai', 'hyundai', 23],
                [23, 'Kia', 'kia', 24],
                [23, 'Genesis', 'genesis', 25],
                [23, 'SsangYong', 'ssangyong', 26],

                // Французские производители
                [23, 'Renault', 'renault', 27],
                [23, 'Peugeot', 'peugeot', 28],
                [23, 'Citroën', 'citroen', 29],
                [23, 'DS Automobiles', 'ds', 30],

                // Итальянские производители
                [23, 'Fiat', 'fiat', 31],
                [23, 'Alfa Romeo', 'alfa-romeo', 32],
                [23, 'Ferrari', 'ferrari', 33],
                [23, 'Lamborghini', 'lamborghini', 34],
                [23, 'Maserati', 'maserati', 35],

                // Шведские производители
                [23, 'Volvo', 'volvo', 36],
                [23, 'Polestar', 'polestar', 37],

                // Китайские производители
                [23, 'Geely', 'geely', 38],
                [23, 'Chery', 'chery', 39],
                [23, 'Great Wall', 'great-wall', 40],
                [23, 'BYD', 'byd', 41],
                [23, 'Changan', 'changan', 42],

                // Российские производители
                [23, 'Lada (ВАЗ)', 'lada', 43],
                [23, 'ГАЗ', 'gaz', 44],
                [23, 'УАЗ', 'uaz', 45],
                [23, 'Москвич', 'moskvich', 46],
                [23, 'Aurus', 'aurus', 47],

                // Люксовые бренды
                [23, 'Bentley', 'bentley', 48],
                [23, 'Rolls-Royce', 'rolls-royce', 49],
                [23, 'Aston Martin', 'aston-martin', 50],
                [23, 'Bugatti', 'bugatti', 51],
                [23, 'McLaren', 'mclaren', 52],
                [23, 'Lotus', 'lotus', 53],
//                коммерческие автомобили
                [23, 'MAN', 'man', 54],
                [23, 'Scania', 'scania', 55],
                [23, 'Iveco', 'iveco', 56],
                [23, 'Volvo Trucks', 'volvo-trucks', 57],
                [23, 'Kamaz', 'kamaz', 58],

                // Типы разъемов (ID=32)
                [32, 'USB-C', 'usb-c', 1],
                [32, 'Lightning', 'lightning', 2],
                [32, 'Micro-USB', 'micro-usb', 3],
                [32, 'USB-A', 'usb-a', 4],
                [32, '3.5mm jack', 'audio-jack', 5],

                // Степень защиты IP (ID=33)
                [33, 'IP20', 'ip20', 1],
                [33, 'IP44', 'ip44', 2],
                [33, 'IP55', 'ip55', 3],
                [33, 'IP65', 'ip65', 4],
                [33, 'IP67', 'ip67', 5],
                [33, 'IP68', 'ip68', 6],

                // Типы бытовой химии (ID=38)
                [38, 'Средство для мытья посуды', 'dish-detergent', 1],
                [38, 'Стиральный порошок', 'laundry-powder', 2],
                [38, 'Кондиционер для белья', 'fabric-softener', 3],
                [38, 'Чистящее средство', 'cleaning-agent', 4],
                [38, 'Полироль', 'polish', 5],
                [38, 'Освежитель воздуха', 'air-freshener', 6],
                [38, 'Средство для стекол', 'glass-cleaner', 7],

                // Для каких поверхностей (ID=39)
                [39, 'Дерево', 'wood', 1],
                [39, 'Металл', 'metal', 2],
                [39, 'Стекло', 'glass', 3],
                [39, 'Пластик', 'plastic', 4],
                [39, 'Керамика', 'ceramic', 5],

                [40, 'Ягодный', 'berry', 1],
                [40, 'Цитрусовый', 'citrus', 2],
                [40, 'Освежающий', 'fresh', 3],
                [40, 'Мультифрукт', 'multifruit', 4],
                [40, 'Мята', 'mint', 5],
                [40, 'Лаванда', 'lavender', 6],
                [40, 'Ваниль', 'vanilla', 7],
                [40, 'Кокос', 'coconut', 8],
                [40, 'Морской бриз', 'ocean-breeze', 9],
                [40, 'Хвойный', 'coniferous', 10],
                [40, 'Цветочный', 'floral', 11],
                [40, 'Кофе', 'coffee', 12],
                [40, 'Шоколад', 'chocolate', 13],
                [40, 'Без запаха', 'odorless', 14],
                [40, 'Корица', 'cinnamon', 15],
                [40, 'Яблоко', 'apples', 16],
                [40, 'Лимон', 'lemon', 17],
                [40, 'Апельсин', 'orange', 18],

                // Языки (ID=46)
                [46, 'Русский', 'russian', 1],
                [46, 'Английский', 'english', 2],
                [46, 'Китайский', 'chinese', 3],
                [46, 'Немецкий', 'german', 4],
                [46, 'Французский', 'french', 5],


            ]
        );






        $this->execute('
        CREATE OR REPLACE FUNCTION update_updated_at_column()
        RETURNS TRIGGER AS $$
        BEGIN
            NEW.updated_at = NOW();
            RETURN NEW;
        END;
        $$ LANGUAGE plpgsql;
        
        ');

        $this->execute('
    CREATE TRIGGER update_attribute_options_updated_at
    BEFORE UPDATE ON brands
    FOR EACH ROW
        EXECUTE FUNCTION update_updated_at_column();
        ');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%attribute_options}}');
    }


}
