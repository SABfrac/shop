<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%offers}}`.
 */
class m250719_104830_create_offers_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%offers}}', [
            'id' => $this->primaryKey()->comment('ID предложения'),

            'product_id' => $this->integer()->notNull()->comment('ID товара (модели)'),
            'vendor_id' => $this->integer()->notNull()->comment('ID продавца (из таблицы vendor)'),

            'price' => $this->decimal(12, 2)->notNull()->comment('Цена'),
            'stock' => $this->integer()->notNull()->defaultValue(0)->comment('Количество на складе'),
            'sku' => $this->string(255)->null()->comment('Артикул продавца (SKU)'),

            'condition' => $this->string(50)->notNull()->defaultValue('new')->comment('Состояние (new, used, refurbished)'),
            'status' => $this->boolean()->notNull()->defaultValue(false)->comment('Активно ли предложение (прошло модерацию)'),
            'sort_order' => $this->integer()->notNull()->defaultValue(0)->comment('Порядок сортировки'),

            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);


        $this->execute("
            CREATE OR REPLACE FUNCTION update_updated_at_column()
            RETURNS TRIGGER AS $$
            BEGIN
               NEW.updated_at = NOW();
               RETURN NEW;
            END;
            $$ language 'plpgsql';
        ");
        $this->execute("
            CREATE TRIGGER update_offers_updated_at
            BEFORE UPDATE ON offers
            FOR EACH ROW
            EXECUTE PROCEDURE update_updated_at_column();
        ");


        // Индекс по товару (чтобы быстро находить все предложения для одного товара)
        $this->createIndex(
            'idx-offers-product_id',
            'offers',
            'product_id'
        );

        // Индекс по продавцу (чтобы быстро находить все предложения одного продавца)
        $this->createIndex(
            'idx-offers-vendor_id',
           'offers',
            'vendor_id'
        );

        $this->createIndex('idx-offers-price', 'offers', 'price');
        $this->createIndex('idx-offers-product_status', 'offers', ['product_id', 'status']); // Для фильтрации активных
        /**
         * Ускоряет выборку только активных предложений для отображения на сайте
         */
        $this->createIndex(
            'idx-offers-is_active',
            'offers',
            'status'
        );

// Связь с таблицей товаров
//        $this->addForeignKey(
//            'fk-offers-product_id',
//            'offers',
//            'product_id',
//            '{{%products}}',
//            'id',
//            'CASCADE', // При удалении товара, удалить все его предложения
//            'RESTRICT'
//        );
//
//        // Связь с таблицей пользователей (продавцов)
//        $this->addForeignKey(
//            'fk-offers-vendor_id',
//            'offers',
//            'vendor_id',
//            '{{%vendors}}',
//            'id',
//            'CASCADE', // При удалении продавца, удалить все его предложения
//            'RESTRICT'
//        );

// Ограничение на допустимые значения состояния товара
        $this->execute("ALTER TABLE offers ADD CONSTRAINT chk_offers_condition_values CHECK (condition IN ('new', 'used', 'discounted'))");
        // Ограничение, чтобы количество на складе не было отрицательным
        $this->execute("ALTER TABLE offers ADD CONSTRAINT chk_offers_stock_positive CHECK (stock >= 0)");



    }



    /**
     * {@inheritdoc}
     */
    public function safeDown()

    {
        $this->dropIndex('idx-offers-is_active', 'offers');
        $this->dropIndex('idx-offers-vendor_id', 'offers');
        $this->dropIndex('idx-offers-product_id', 'offers');

        $this->dropTable('offers');
    }
}
