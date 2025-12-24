<?php

use yii\db\Migration;

class m250528_190500_create_table_attributes_product_attribute_values_product_flat extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Таблица атрибутов
        $this->createTable('attributes', [
            'id' => $this->primaryKey(),
            'name' => 'citext NOT NULL',
            'type' => $this->string()->notNull(), // string, integer, float, boolean
            'code' => $this->string(64)->notNull()->defaultValue(''),
            'is_filterable' => $this->boolean()->defaultValue(false),
            'is_required' => $this->boolean()->defaultValue(false),
            'status' => $this->smallInteger()->notNull()->defaultValue(1)->comment('0=inactive, 1=active'),
        ]);
        $commonAttributes = [
            ['Название', 'string', true, true],
            ['Описание', 'string', false, false],
            ['Бренд', 'select', true, false],
            ['Страна производитель', 'select', false, false],
            ['Гарантия', 'integer', false, false],
            ['В наличии', 'boolean', true, true],
        ];

// Одежда и обувь
        $clothingAttributes = [
            ['Цвет', 'select', true, true],
            ['Размер (РФ)', 'select', true, true],
            ['Размер (EU)', 'select', true, false],
            ['Размер (US)', 'select', true, false],
            ['Материал', 'select', true, false],
            ['Сезон', 'select', true, false],
            ['Пол', 'select', true, true],
        ];

// Электроника и гаджеты
        $electronicsAttributes = [
            ['Модель', 'string', true, true],
            ['Процессор', 'select', true, false],
            ['Оперативная память (ГБ)', 'select', true, false],
            ['Встроенная память (ГБ)', 'select', true, false],
            ['Операционная система', 'select', true, false],
            ['Диагональ экрана (дюймы)', 'float', true, false],
            ['Аккумулятор (мАч)', 'integer', false, false],
            ['Вес (кг)', 'float', false, false],
        ];

// Автозапчасти (общие)
        $autoPartsAttributes = [
            ['Артикул', 'string', true, true],
            ['Производитель', 'select', true, true],
            ['Модель автомобиля', 'select', true, true],
            ['Год выпуска', 'integer', true, false],
            ['Совместимость', 'string', false, false],
            ['Вес (кг)', 'float', false, false],
            ['Материал', 'string', false, false],
        ];

// Автоэлектрика (дополнительные атрибуты)
        $autoElectricsAttributes = [
            ['Напряжение (В)', 'float', true, false],
            ['Ток (А)', 'float', false, false],
            ['Мощность (Вт)', 'float', true, false],
            ['Тип разъема', 'select', true, false],
            ['Степень защиты (IP)', 'select', false, false],
        ];

// Бытовые запчасти (для техники)
        $householdPartsAttributes = [
            ['Тип запчасти', 'string', true, true],
            ['Совместимая техника', 'string', true, false],
            ['Количество в упаковке', 'integer', false, false],
        ];

// Бытовая химия и расходники
        $householdChemistryAttributes = [
            ['Объем (л/кг)', 'float', true, true],
            ['Тип', 'select', true, true],
            ['Для каких поверхностей', 'select', true, false],
            ['Аромат', 'select', false, false],
            ['Экологичность', 'boolean', false, false],
        ];

// Книги и медиа
        $booksMediaAttributes = [
            ['Автор', 'string', true, true],
            ['ISBN', 'string', true, false],
            ['Год издания', 'integer', false, false],
            ['Издательство', 'string', false, false],
            ['Язык', 'select', true, false],
            ['Количество страниц', 'integer', false, false],
            ['Поддерживаемые типы RAM', 'select', true, false],
        ];

// ТВ
        $televisionAttributes = [
            ['Разрешение', 'select', true, true],
            ['Тип матрицы', 'select', true, true],
            ['Smart TV', 'select', true, true],
            ['HDR', 'select', true, false],
            ['Частота обновления (Гц)', 'select', true, false],
            ['Габариты (ШxВxГ)', 'string', false, false],
            ['Поддержка Dolby Vision', 'select', true, false],
            ['Количество HDMI-портов', 'select', false, false],
            ['Количество USB-портов', 'select', false, false],
        ];

        $peripheryAttributes = [
            ['Раскладка', 'select', true, true],
            ['Тип переключателей', 'select', false, false],
            ['Форм-фактор', 'select', false, true],
            ['Подсветка', 'boolean', false, true],
            ['Тип сенсора', 'select', true, true],
            ['DPI (макс)', 'integer', true, true],
            ['Частота опроса (Гц)', 'select', false, false],
            ['Количество кнопок', 'integer', false, false],
            ['Состав комплекта', 'select', true, false],
            ['Толщина (мм)', 'float', false, false],
            ['Диапазон частот (Гц)', 'string', false, false],
            ['Чувствительность (дБ)', 'float', false, false],
            ['Направленность', 'select', false, true],
            ['Фантомное питание', 'boolean', false, false],
            ['Микрофон', 'boolean', false, true],
            ['Bluetooth', 'boolean', false, true],
            ['Активная область (мм)', 'string', true, false],
            ['Уровни нажатия', 'integer', true, true],
            ['Разрешение пера (LPI)', 'integer', false, true],
            ['Количество клавиш', 'integer', false, false],
            ['Скорость передачи (МБ/с)', 'float', false, true],
            ['Частота кадров (fps)', 'select', true, false],
            ['Угол обзора (°)', 'integer', false, true],
            ['Автофокус', 'boolean', false, true],
            ['Беспроводная', 'boolean', false, true],
            ['Длина кабеля (м)', 'float', false, true],
            ['Импеданс (Ом)', 'integer', false, true],
        ];

        $categoryAttributes = [

            // 120: Мебель (furniture)
            120 => [
                ['Артикул', 'string', true, true],
               
                ['Тип мебели', 'select', true, true],
                ['Материал', 'select', true, false],
                ['Цвет', 'select', true, false],
                ['Стиль', 'select', true, false],
                ['Помещение', 'select', true, false],
                ['Размеры (Ш×Г×В), см', 'string', false, false],
                ['Вес, кг', 'float', false, false],
                ['Максимальная нагрузка, кг', 'integer', true, false],
               
                ['Гарантия, мес', 'integer', false, false],
            ],

            // 121: Текстиль (textiles)
            121 => [
                ['Артикул', 'string', true, true],
               
                ['Тип текстиля', 'select', true, true],
                ['Материал', 'select', true, false],
                ['Размер', 'string', true, false],
                ['Плотность, г/м²', 'integer', true, false],
                ['Цвет', 'select', true, false],
                ['Узор/принт', 'select', true, false],
                ['Сезон', 'select', true, false],
                ['Состав', 'string', false, false],
                ['Способ ухода', 'select', false, false],
               
            ],

            // 122: Посуда (tableware)
            122 => [
                ['Артикул', 'string', true, true],
               
                ['Тип посуды', 'select', true, true],
                ['Материал', 'select', true, false],
                ['Назначение', 'select', true, false],
                ['Объем, мл', 'integer', true, false],
                ['Диаметр, см', 'float', true, false],
                ['Можно для СВЧ', 'select', true, false],
                ['Можно мыть в ПММ', 'select', true, false],
                ['Совместимость с плитами', 'select', true, false],
               
            ],

            // 123: Декор (home-decor)
            123 => [
                ['Артикул', 'string', true, true],
               
                ['Тип декора', 'select', true, true],
                ['Материал', 'select', true, false],
                ['Цвет', 'select', true, false],
                ['Стиль', 'select', true, false],
                ['Тематика/сезон', 'select', true, false],
                ['Помещение', 'select', true, false],
                ['Размеры (Д×Ш×В), см', 'string', false, false],
                ['Вес, кг', 'float', false, false],
               
            ],

            // 124: Инструменты (tools)
            124 => [
                ['Артикул', 'string', true, true],
               
                ['Тип инструмента', 'select', true, true],
                ['Назначение', 'select', true, false],
                ['Питание', 'select', true, false],
                ['Мощность, Вт', 'integer', true, false],
                ['Напряжение, В', 'integer', true, false],
                ['Обороты, об/мин', 'integer', true, false],
                ['Диаметр диска/патрона, мм', 'integer', true, false],
                ['Материал', 'select', true, false],
                ['Вес, кг', 'float', false, false],
                ['Комплектация', 'string', false, false],
               
            ],

            // 125: Садовый инвентарь (garden-tools)
            125 => [
                ['Артикул', 'string', true, true],
               
                ['Тип инвентаря', 'select', true, true],
                ['Назначение', 'select', true, false],
                ['Материал рукояти', 'select', true, false],
                ['Материал рабочей части', 'select', true, false],
                ['Длина, см', 'integer', true, false],
                ['Ширина рабочей части, см', 'integer', true, false],
                ['Вес, кг', 'float', false, false],
                ['Телескопическая ручка', 'select', true, false],
               
            ],

            // 126: Косметика (cosmetics)
            126 => [
                ['Артикул', 'string', true, true],
               
                ['Тип средства', 'select', true, true],
                ['Назначение', 'select', true, false],
                ['Тип кожи/волос', 'select', true, false],
                ['Объем, мл', 'integer', true, false],
                ['Активные компоненты', 'string', false, false],
                ['Отдушка/аромат', 'select', true, false],
                ['Тип упаковки', 'select', true, false],
                ['Возрастная категория', 'select', true, false],
                ['Пол', 'select', true, false],
               
            ],

            // 127: Парфюмерия (perfume)
            127 => [
                ['Артикул', 'string', true, true],
               
                ['Семейство ароматов', 'select', true, true],
                ['Концентрация (EDT/EDP и т.п.)', 'select', true, true],
                ['Объем, мл', 'integer', true, true],
                ['Ноты аромата', 'string', false, false],
                ['Пол', 'select', true, false],
               
            ],

            // 128: Уход за волосами (hair-care)
            128 => [
                ['Артикул', 'string', true, true],
               
                ['Тип средства', 'select', true, true],
                ['Тип волос', 'select', true, false],
                ['Эффект', 'select', true, false],
                ['Объем, мл', 'integer', true, false],
                ['Без сульфатов', 'select', true, false],
                ['Без силиконов', 'select', true, false],
                ['Активные компоненты', 'string', false, false],
               
            ],

            // 129: Гигиена (hygiene)
            129 => [
                ['Артикул', 'string', true, true],
               
                ['Тип товара', 'select', true, true],
                ['Назначение', 'select', true, false],
                ['Форма выпуска', 'select', true, false],
                ['Вес/объем', 'string', false, false],
                ['Количество в упаковке, шт', 'integer', true, false],
                ['Аромат', 'select', true, false],
                ['Тип упаковки', 'select', true, false],
                ['Гипоаллергенный', 'select', true, false],
               
            ],

            // 130: Витамины (vitamins)
            130 => [
                ['Артикул', 'string', true, true],
               
                ['Форма выпуска', 'select', true, true],
                ['Назначение', 'select', true, false],
                ['Активное вещество/комплекс', 'string', false, false],
                ['Дозировка, мг', 'integer', true, false],
                ['Количество в упаковке, шт', 'integer', true, false],
                ['Курс приема, дней', 'integer', true, false],
                ['Возрастная категория', 'select', true, false],
                ['Пол', 'select', true, false],
               
            ],

            // 131: Медицинские товары (medical)
            131 => [
                ['Артикул', 'string', true, true],
               
                ['Тип изделия', 'select', true, true],
                ['Назначение', 'select', true, false],
                ['Размер', 'string', true, false],
                ['Материал', 'select', true, false],
                ['Стерильность', 'select', true, false],
                ['Класс/степень защиты', 'select', true, false],
                ['Одноразовый/многоразовый', 'select', true, false],
                ['Гарантия, мес', 'integer', false, false],
               
            ],

            // 132: Фитнес (fitness)
            132 => [
                ['Артикул', 'string', true, true],
               
                ['Тип товара', 'select', true, true],
                ['Тип нагрузки', 'select', true, false],
                ['Материал', 'select', true, false],
                ['Максимальная нагрузка, кг', 'integer', true, false],
                ['Вес, кг', 'float', false, false],
                ['Длина, см', 'integer', true, false],
                ['Цвет', 'select', true, false],
                ['Комплектация', 'string', false, false],
               
            ],

            // 133: Велоспорт (cycling)
            133 => [
                ['Артикул', 'string', true, true],
               
                ['Тип товара', 'select', true, true],
                ['Тип велосипеда', 'select', true, false],
                ['Материал рамы', 'select', true, false],
                ['Размер рамы', 'select', true, false],
                ['Размер колес, "', 'float', true, false],
                ['Количество скоростей, шт', 'integer', true, false],
                ['Тип тормозов', 'select', true, false],
                ['Вес, кг', 'float', false, false],
                ['Рост/ростовка, см', 'integer', true, false],
                ['Пол', 'select', true, false],
               
            ],

            // 134: Туризм (tourism)
            134 => [
                ['Артикул', 'string', true, true],
               
                ['Тип товара', 'select', true, true],
                ['Назначение', 'select', true, false],
                ['Вместимость, чел', 'integer', true, false],
                ['Объем/литраж, л', 'float', true, false],
                ['Водонепроницаемость, мм', 'integer', true, false],
                ['Температура комфорта, °C', 'integer', true, false],
                ['Материал', 'select', true, false],
                ['Вес, кг', 'float', false, false],
                ['Сезонность', 'select', true, false],
               
            ],

            // 135: Рыбалка (fishing)
            135 => [
                ['Артикул', 'string', true, true],
               
                ['Тип снасти/товара', 'select', true, true],
                ['Класс/тест, г', 'integer', true, false],
                ['Длина, м', 'float', true, false],
                ['Количество секций, шт', 'integer', true, false],
                ['Материал бланка/катушки', 'select', true, false],
                ['Передаточное число', 'string', true, false],
                ['Диаметр лески, мм', 'float', true, false],
                ['Вес, г', 'integer', false, false],
               
            ],

            // 136: Зимние виды спорта (winter-sports)
            136 => [
                ['Артикул', 'string', true, true],
               
                ['Вид спорта', 'select', true, true],
                ['Тип товара', 'select', true, true],
                ['Длина, см', 'integer', true, false],
                ['Ростовка/размер', 'select', true, false],
                ['Жесткость', 'select', true, false],
                ['Ширина талии, мм', 'integer', true, false],
                ['Сезон', 'select', true, false],
                ['Комплектация', 'string', false, false],
               
            ],

            // 137: Игры (games)
            137 => [
                ['Артикул', 'string', true, true],
                ['Издатель/бренд', 'select', true, true],
                ['Тип игры', 'select', true, true],
                ['Жанр', 'select', true, false],
                ['Язык', 'select', true, false],
                ['Возраст, лет', 'integer', true, false],
                ['Количество игроков, чел', 'integer', true, false],
                ['Время партии, мин', 'integer', true, false],
                ['Комплектация', 'string', false, false],
               
            ],

            // 138: Игрушки (toys)
            138 => [
                ['Артикул', 'string', true, true],
               
                ['Тип игрушки', 'select', true, true],
                ['Материал', 'select', true, false],
                ['Возраст, лет', 'integer', true, false],
                ['Пол', 'select', true, false],
                ['Питание', 'select', true, false],
                ['Звук/свет', 'select', true, false],
                ['Цвет', 'select', true, false],
                ['Размеры (Д×Ш×В), см', 'string', false, false],
                ['Сертификаты', 'string', false, false],
               
            ],

            // 139: Коляски (strollers)
            139 => [
                ['Артикул', 'string', true, true],
               
                ['Тип коляски', 'select', true, true],
                ['Возраст, мес', 'integer', true, false],
                ['Максимальная нагрузка, кг', 'integer', true, false],
                ['Вес коляски, кг', 'float', false, false],
                ['Тип складывания', 'select', true, false],
                ['Количество колес, шт', 'integer', true, false],
                ['Диаметр колес, см', 'integer', true, false],
                ['Амортизация', 'select', true, false],
                ['Комплектация', 'select', true, false],
               
            ],

            // 140: Детская мебель (kids-furniture)
            140 => [
                ['Артикул', 'string', true, true],
               
                ['Тип мебели', 'select', true, true],
                ['Возраст, лет', 'integer', true, false],
                ['Материал', 'select', true, false],
                ['Цвет', 'select', true, false],
                ['Размеры (Ш×Г×В), см', 'string', false, false],
                ['Максимальная нагрузка, кг', 'integer', true, false],
                ['Безопасность (бортики, крепления)', 'select', true, false],
               
            ],

            // 141: Питание (baby-food)
            141 => [
                ['Артикул', 'string', true, true],
               
                ['Тип питания', 'select', true, true],
                ['Возраст, мес', 'integer', true, true],
                ['Вкус/вид', 'select', true, false],
                ['Форма выпуска', 'select', true, false],
                ['Вес/объем', 'string', false, false],
                ['Состав', 'string', false, false],
                ['Особенности (без сахара и т.п.)', 'select', true, false],
               
            ],

            // 142: Гигиена (baby-hygiene)
            142 => [
                ['Артикул', 'string', true, true],
               
                ['Тип средства', 'select', true, true],
                ['Возраст, мес', 'integer', true, false],
                ['Объем, мл', 'integer', true, false],
                ['Аромат', 'select', true, false],
                ['Гипоаллергенный', 'select', true, false],
                ['Количество в упаковке, шт', 'integer', true, false],
                ['Тип упаковки', 'select', true, false],
               
            ],

            // 143: Школа (school)
            143 => [
                ['Артикул', 'string', true, true],
               
                ['Тип товара', 'select', true, true],
                ['Класс/возраст', 'select', true, false],
                ['Формат/размер', 'select', true, false],
                ['Материал', 'select', true, false],
                ['Количество листов, шт', 'integer', true, false],
                ['Объем/литраж рюкзака, л', 'float', true, false],
                ['Цвет', 'select', true, false],
                ['Тематика/принт', 'select', true, false],
               
            ],

            // 144: Бакалея (groceries)
            144 => [
                ['Артикул', 'string', true, true],
               
                ['Тип продукта', 'select', true, true],
                ['Вес, г', 'integer', true, false],
                ['Упаковка', 'select', true, false],
                ['Состав', 'string', false, false],
                ['Энергетическая ценность, ккал/100 г', 'integer', true, false],
                ['Белки, г/100 г', 'float', true, false],
                ['Жиры, г/100 г', 'float', true, false],
                ['Углеводы, г/100 г', 'float', true, false],
                
                ['Срок годности', 'string', false, false],
            ],

            // 145: Молочные продукты (dairy)
            145 => [
                ['Артикул', 'string', true, true],
               
                ['Тип продукта', 'select', true, true],
                ['Жирность, %', 'float', true, false],
                ['Масса нетто, г', 'integer', true, false],
                ['Объем, мл', 'integer', true, false],
                ['Состав', 'string', false, false],
                ['Упаковка', 'select', true, false],
                ['Срок годности, сут', 'integer', true, false],
                ['Температура хранения, °C', 'float', true, false],
                
            ],

            // 146: Мясо и птица (meat)
            146 => [
                ['Артикул', 'string', true, true],
                ['Производитель', 'select', true, true],
                ['Вид мяса', 'select', true, true],
                ['Часть туши', 'select', true, false],
                ['Тип продукта', 'select', true, false],
                ['Масса нетто, г', 'integer', true, true],
                ['Упаковка', 'select', true, false],
                
                ['Срок хранения, сут', 'integer', true, false],
                ['Температурный режим, °C', 'string', false, false],
            ],

            // 147: Овощи и фрукты (vegetables-fruits)
            147 => [
                ['Артикул', 'string', true, true],
                ['Производитель', 'select', true, true],
                ['Тип продукта', 'select', true, true],
                ['Сорт/вид', 'select', true, false],
                
                ['Категория/калибр', 'select', true, false],
                ['Масса, г', 'integer', true, false],
                ['Способ выращивания', 'select', true, false],
                ['Сезонность', 'select', true, false],
            ],

            // 148: Напитки (beverages)
            148 => [
                ['Артикул', 'string', true, true],
               
                ['Тип напитка', 'select', true, true],
                ['Объем, л', 'float', true, true],
                ['Упаковка', 'select', true, false],
                ['Крепость, %', 'float', true, false],
                ['Вкус/аромат', 'select', true, false],
                ['Сахар, г/100 мл', 'float', true, false],
                ['Энергетическая ценность, ккал/100 мл', 'integer', true, false],
                
            ],

            // 149: Сладости (sweets)
            149 => [
                ['Артикул', 'string', true, true],
               
                ['Тип сладостей', 'select', true, true],
                ['Начинка/вкус', 'select', true, false],
                ['Масса нетто, г', 'integer', true, false],
                ['Какао, %', 'float', true, false],
                ['Состав', 'string', false, false],
                ['Упаковка', 'select', true, false],
                ['Особенности', 'select', true, false],
                
            ],

            // 150: Средства для стирки (laundry)
            150 => [
                ['Артикул', 'string', true, true],
               
                ['Тип средства', 'select', true, true],
                ['Форма выпуска', 'select', true, false],
                ['Назначение', 'select', true, false],
                ['Для типа ткани', 'select', true, false],
                ['Температура стирки, °C', 'integer', true, false],
                ['Объем, мл', 'integer', true, false],
                ['Концентрат', 'select', true, false],
                ['Аромат', 'select', true, false],
               
            ],

            // 151: Средства для мытья посуды (dishwashing)
            151 => [
                ['Артикул', 'string', true, true],
               
                ['Тип средства', 'select', true, true],
                ['Совместимо с ПММ', 'select', true, false],
                ['Объем, мл', 'integer', true, false],
                ['Аромат', 'select', true, false],
                ['Гипоаллергенный', 'select', true, false],
                ['Биоразлагаемость', 'select', true, false],
               
            ],

            // 152: Чистящие средства (cleaning)
            152 => [
                ['Артикул', 'string', true, true],
               
                ['Тип средства', 'select', true, true],
                ['Назначение', 'select', true, false],
                ['Для поверхностей', 'select', true, false],
                ['Объем, мл', 'integer', true, false],
                ['Аромат', 'select', true, false],
                ['Концентрат', 'select', true, false],
                ['С антисептиком', 'select', true, false],
               
            ],

            // 153: Освежители воздуха (air-fresheners)
            153 => [
                ['Артикул', 'string', true, true],
               
                ['Тип освежителя', 'select', true, true],
                ['Аромат', 'select', true, false],
                ['Форма выпуска', 'select', true, false],
                ['Продолжительность действия, дней', 'integer', true, false],
                ['Объем, мл', 'integer', true, false],
                ['Помещение', 'select', true, false],
                ['Интенсивность аромата', 'select', true, false],
               
            ],

            // 219: Приборные панели (dashboards)
            219 => [
                ['Артикул', 'string', true, true],
                ['Производитель', 'select', true, true],
                ['Марка автомобиля', 'select', true, true],
                ['Модель автомобиля', 'select', true, true],
                ['Поколение/кузов', 'string', true, false],
                ['Год выпуска', 'integer', true, false],
                ['Тип панели', 'select', true, true],
                ['Совместимость', 'select', true, false],
                ['Подсветка', 'select', true, false],
                ['Материал', 'select', true, false],
                ['Состояние', 'select', true, false],
                ['Гарантия, мес', 'integer', false, false],
               
            ],

            // 154: Дезинфицирующие средства (disinfectants)
            154 => [
                ['Артикул', 'string', true, true],
               
                ['Тип средства', 'select', true, true],
                ['Концентрация спирта, %', 'float', true, false],
                ['Объем, мл', 'integer', true, false],
                ['Форма выпуска', 'select', true, false],
                ['Назначение', 'select', true, false],
                ['Для поверхностей/кожи', 'select', true, false],
                ['Аромат', 'select', true, false],
                ['Гипоаллергенный', 'select', true, false],
               
            ],

            // 155: Средства от насекомых (pest-control)
            155 => [
                ['Артикул', 'string', true, true],
               
                ['Тип средства', 'select', true, true],
                ['Вид вредителя', 'select', true, true],
                ['Способ применения', 'select', true, false],
                ['Площадь обработки, м²', 'float', true, false],
                ['Вес/объем', 'string', false, false],
                ['Место использования', 'select', true, false],
                ['Действующее вещество', 'string', false, false],
                ['Срок защиты, дней', 'integer', true, false],
               
            ],

        ];

        $categoryAttributesFlat = [];
        foreach ($categoryAttributes as $categoryId => $attributes) {
            foreach ($attributes as $attribute) {
                $categoryAttributesFlat[] = [
                    $attribute[0], // name (например, 'Артикул')
                    $attribute[1], // type (например, 'string')
                    $attribute[2], // is_filterable (например, true)
                    $attribute[3]  // is_required (например, true)

                ];
            }
        }

        // Собираем ВСЕ атрибуты в один массив
        $allAttributes = array_merge(
            $commonAttributes,
            $clothingAttributes,
            $electronicsAttributes,
            $autoPartsAttributes,
            $autoElectricsAttributes,
            $householdPartsAttributes,
            $householdChemistryAttributes,
            $booksMediaAttributes,
            $televisionAttributes,
            $peripheryAttributes,
            $categoryAttributesFlat
        );

        // Вставляем данные в таблицу `attributes`
        $this->batchInsert('attributes',
            ['name', 'type', 'is_filterable', 'is_required'],
            $allAttributes
        );

        $this->createIndex('idx-attributes-name', '{{%attributes}}', 'name');

        $this->execute('CREATE EXTENSION IF NOT EXISTS unaccent');
        $this->execute("
            CREATE OR REPLACE FUNCTION translit_ru_to_en(input_text text)
            RETURNS text AS $$
           DECLARE
    result text := input_text;
BEGIN
    -- Многосимвольные замены (важно делать сначала, чтобы не нарушить односимвольные)
    result := replace(result, 'Щ', 'Shch');
    result := replace(result, 'щ', 'shch');
    result := replace(result, 'Ё', 'Yo');
    result := replace(result, 'ё', 'yo');
    result := replace(result, 'Ж', 'Zh');
    result := replace(result, 'ж', 'zh');
    result := replace(result, 'Ц', 'Ts');
    result := replace(result, 'ц', 'ts');
    result := replace(result, 'Ч', 'Ch');
    result := replace(result, 'ч', 'ch');
    result := replace(result, 'Ш', 'Sh');
    result := replace(result, 'ш', 'sh');
    result := replace(result, 'Ю', 'Yu');
    result := replace(result, 'ю', 'yu');
    result := replace(result, 'Я', 'Ya');
    result := replace(result, 'я', 'ya');
    result := replace(result, 'Х', 'Kh');
    result := replace(result, 'х', 'kh');

    -- Односимвольные замены
    result := translate(result,
        'АБВГДЕЗИЙКЛМНОПРСТУФЫЭЪЬабвгдезийклмнопрстуфыэъь',
        'ABVGDEZIYKLMNOPRSTUFYE  abvgdezIykLMNOPRSTUFYE  '
    );

    RETURN lower(result);
END;
$$ LANGUAGE plpgsql IMMUTABLE;
        ");

        // Шаг 2: Обновляем code с использованием транслитерации
        $this->execute("
            WITH ranked AS (
        SELECT 
            id,
            COALESCE(
                NULLIF(
                    REGEXP_REPLACE(
                        REGEXP_REPLACE(
                            translit_ru_to_en(name),
                            '[^a-z0-9\s]', '', 'g'
                        ),
                        '\s+', '_', 'g'
                    ),
                    ''
                ),
                'attr_' || id::text
            ) AS base_code,
            ROW_NUMBER() OVER (
                PARTITION BY COALESCE(
                    NULLIF(
                        REGEXP_REPLACE(
                            REGEXP_REPLACE(
                                translit_ru_to_en(name),
                                '[^a-z0-9\s]', '', 'g'
                            ),
                            '\s+', '_', 'g'
                        ),
                        ''
                    ),
                    'attr_' || id::text
                )
                ORDER BY id
            ) AS rn
        FROM {{%attributes}}
        WHERE code = '' OR code IS NULL
    )
    UPDATE {{%attributes}} a
    SET code = r.base_code || CASE WHEN r.rn > 1 THEN '_' || r.rn ELSE '' END
    FROM ranked r
    WHERE a.id = r.id;
");


//         Шаг 3 (опционально): Удаляем функцию, если не нужна дальше
         $this->execute("DROP FUNCTION IF EXISTS translit_ru_to_en(text);");

        $this->execute("
    CREATE UNIQUE INDEX idx_attributes_code_unique 
    ON {{%attributes}} (code) 
    WHERE code IS NOT NULL AND code != '';
");



        $this->createTable('product_attribute_values', [
            'id' => $this->primaryKey(),
            'global_product_id' => $this->integer()->notNull(),
            'attribute_id' => $this->integer()->notNull(),
            'value_string' => $this->string()->null(),
            'value_int' => $this->integer()->null(),
            'value_float' => $this->float()->null(),
            'value_bool' => $this->boolean()->null(),
            'category_attribute_options_id' => $this->integer()->null(), // если значение из списка
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);
        $this->createIndex('idx-product_attribute_values-product_id', 'product_attribute_values', 'global_product_id');
        $this->createIndex('idx-product_attribute_values-attribute_id', 'product_attribute_values', 'attribute_id');
        $this->createIndex('idx-product_attribute_values-product_attribute', 'product_attribute_values', ['global_product_id', 'attribute_id']);

        $this->execute("
            ALTER TABLE {{%product_attribute_values}}
            ADD CONSTRAINT single_value_check
            CHECK (num_nonnulls(value_string, value_int, value_float, value_bool, category_attribute_options_id) = 1)
        ");



        // Денормализованная таблица для часто используемых атрибутов
        $this->createTable('product_flat', [
            'id' => $this->primaryKey(),
            'global_product_id'=> $this->integer()->notNull(),
            'category_id' => $this->integer()->notNull(),
            'name' => $this->string()->notNull(),
            'price' => $this->decimal(10, 2)->notNull(),
            'brand_id' => $this->integer(),
            'vendor_id' => $this->integer(),
            'stock' => $this->integer(),
            'status' => $this->tinyInteger(),
            'color' => $this->string(50),
            'size' => $this->string(50),
            'weight' => $this->decimal(10, 2),
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),

        ]);

        $this->createIndex('idx_product_flat_product_id', 'product_flat', 'global_product_id');
        $this->createIndex('idx_product_flat_category_id', 'product_flat', 'category_id');
        $this->createIndex('idx_product_flat_price', 'product_flat', 'price');


        $this->execute("
        CREATE OR REPLACE FUNCTION update_updated_at_column()
        RETURNS TRIGGER AS $$
        BEGIN
            NEW.updated_at = NOW();
            RETURN NEW;
        END;
        $$ language 'plpgsql';
    ");

        // Триггер для обновления updated_at
        $this->execute("
        CREATE TRIGGER trigger_update_updated_at
        BEFORE UPDATE ON product_flat
        FOR EACH ROW
        EXECUTE PROCEDURE update_updated_at_column();
    ");
        $this->execute("
        CREATE TRIGGER trigger_update_product_attribute_values_updated_at
        BEFORE UPDATE ON product_attribute_values
        FOR EACH ROW
        EXECUTE PROCEDURE update_updated_at_column();
");


    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%product_flat}}');
        $this->dropTable('{{%product_attribute_values}}');
        $this->truncateTable('attributes');
        $this->dropTable('{{%attributes}}');

    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250528_190500_create_table_attributes_product_attribute_values_product_flat cannot be reverted.\n";

        return false;
    }
    */
}
