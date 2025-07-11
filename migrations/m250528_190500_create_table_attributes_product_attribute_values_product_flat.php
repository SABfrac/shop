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
            'name' => $this->string()->notNull(),
            'type' => $this->string()->notNull(), // string, integer, float, boolean
            'is_filterable' => $this->boolean()->defaultValue(false),
            'is_required' => $this->boolean()->defaultValue(false),
        ]);
        $commonAttributes = [
            ['Название', 'string', true, true],
            ['Описание', 'string', false, false],
            ['Бренд', 'string', true, false],
            ['Страна производитель', 'string', false, false],
            ['Гарантия', 'string', false, false],
            ['В наличии', 'boolean', true, true],
        ];

        // Одежда и обувь
        $clothingAttributes = [
            ['Цвет', 'string', true, true],
            ['Размер (РФ)', 'string', true, true],
            ['Размер (EU)', 'string', true, false],
            ['Размер (US)', 'string', true, false],
            ['Материал', 'string', true, false],
            ['Сезон', 'string', true, false],
            ['Пол', 'string', true, true],
        ];

        // Электроника и гаджеты
        $electronicsAttributes = [
            ['Модель', 'string', true, true],
            ['Процессор', 'string', true, false],
            ['Оперативная память (ГБ)', 'integer', true, false],
            ['Встроенная память (ГБ)', 'integer', true, false],
            ['Операционная система', 'string', true, false],
            ['Диагональ экрана (дюймы)', 'float', true, false],
            ['Аккумулятор (мАч)', 'integer', false, false],
            ['Вес (кг)', 'float', false, false],
        ];

        // Автозапчасти (общие)
        $autoPartsAttributes = [
            ['Артикул', 'string', true, true],
            ['Производитель', 'string', true, true],
            ['Модель автомобиля', 'string', true, true],
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
            ['Тип разъема', 'string', true, false],
            ['Степень защиты (IP)', 'string', false, false],
        ];

        // Бытовые запчасти (для техники)
        $householdPartsAttributes = [
            ['Тип запчасти', 'string', true, true], // Фильтр, ремень, подшипник и т.д.
            ['Совместимая техника', 'string', true, false],
            ['Количество в упаковке', 'integer', false, false],
        ];

        // Бытовые химия и расходники
        $householdChemistryAttributes = [
            ['Объем (л/кг)', 'float', true, true],
            ['Тип', 'string', true, true], // Средство для мытья, очиститель и т.д.
            ['Для каких поверхностей', 'string', true, false],
            ['Аромат', 'string', false, false],
            ['Экологичность', 'boolean', false, false],
        ];

        // Книги и медиа
        $booksMediaAttributes = [
            ['Автор', 'string', true, true],
            ['ISBN', 'string', true, false],
            ['Год издания', 'integer', false, false],
            ['Издательство', 'string', false, false],
            ['Язык', 'string', true, false],
            ['Количество страниц', 'integer', false, false],
            ['Поддерживаемые типы RAM', 'string', true, false],
        ];

        // Собираем ВСЕ атрибуты в один массив
        $allAttributes = array_merge(
            $commonAttributes,
            $clothingAttributes,
            $electronicsAttributes,
            $autoPartsAttributes,
            $autoElectricsAttributes,
            $householdPartsAttributes,
            $householdChemistryAttributes,
            $booksMediaAttributes
        );

        // Вставляем данные в таблицу `attributes`
        $this->batchInsert('attributes',
            ['name', 'type', 'is_filterable', 'is_required'],
            $allAttributes
        );

        $this->createIndex('idx-attributes-name', '{{%attributes}}', 'name');

        // Значения атрибутов
        $this->createTable('product_attribute_values', [
            'id' => $this->primaryKey(),
            'product_id' => $this->integer()->notNull(),
            'attribute_id' => $this->integer()->notNull(),
            'value_id' => $this->integer(),
        ]);

        // Денормализованная таблица для часто используемых атрибутов
        $this->createTable('product_flat', [
            'id' => $this->primaryKey(),
            'product_id' => $this->integer()->notNull(),
            'name' => $this->string()->notNull(),
            'price' => $this->decimal(10, 2)->notNull(),
            'brand_id' => $this->integer(),
            'brand' => $this->string(),
            'color' => $this->string(),
            'size' => $this->string(),
            'weight' => $this->decimal(10, 2),
            'search_vector' => 'tsvector',
        ]);


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
