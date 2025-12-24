<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%offers_product_skus}}`.
 */
class m250904_215858_create_offers_product_skus_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%product_skus}}', [
            'id' => $this->primaryKey(),
            'global_product_id' => $this->integer()->notNull(),
            // Хэш нормализованной комбинации вариативных атрибутов
            'variant_hash' => $this->string(64)->notNull(),
            // Денормализованное хранение для быстрых чтений/индексов
            'variant_values' => $this->json()->notNull()->defaultValue('{}'),
            'barcode' => $this->string(32)->null()->comment('GTIN/EAN/UPC (если есть)'),
            'status' => $this->smallInteger()->notNull()->defaultValue(1),
            'created_at' => $this->dateTime()->defaultExpression('NOW()'),
            'updated_at' => $this->dateTime()->defaultExpression('NOW()'),
        ]);

        $this->createIndex(
                            'idx_product_skus_product_id',
                            'product_skus',
                         'global_product_id'
        );

        $this->createIndex(
                           'uidx_product_skus_product_id_variant_hash',
                           'product_skus',
                                ['global_product_id', 'variant_hash'], true
        );

        $this->createIndex(
                      'idx_product_skus_status',
                      'product_skus',
                    'status',
                      false,
                            'WHERE status = 1'
        );


        $this->addForeignKey(
            'fk_product_skus_product',
            '{{%product_skus}}',
            'global_product_id',
            '{{%global_products}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createTable('{{%offers}}', [
            'id' => $this->primaryKey()->comment('ID предложения'),
            'vendor_sku' => $this->string(100)->null()->comment('Глобальный SKU-код (если нужен)'),
            'sku_id' => $this->integer()->notNull()->comment('ID товара (модели)'),
            'vendor_id' => $this->integer()->notNull()->comment('ID продавца (из таблицы vendor)'),
            'price' => $this->decimal(12, 2)->notNull()->comment('Цена'),
            'stock' => $this->integer()->notNull()->defaultValue(0)->comment('Количество на складе'),
            'warranty'=> $this->integer()->defaultValue(0),
            'condition' => $this->string(50)->notNull()->defaultValue('new')->comment('Состояние (new, used, refurbished)'),
            'status' => $this->tinyInteger('status')->defaultValue(0)->comment('0 - Неактивно, 1 - Активно, 2 - На модерации'),
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

        $this->execute("
    CREATE TRIGGER update_product_skus_updated_at
    BEFORE UPDATE ON product_skus
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();
");


        // Индекс по товару (чтобы быстро находить все предложения для одного товара)
        $this->createIndex(
            'idx-offers-sku_id',
            'offers',
            'sku_id'
        );

        // Индекс по продавцу (чтобы быстро находить все предложения одного продавца)
        $this->createIndex(
            'idx-offers-vendor_id',
            'offers',
            'vendor_id'
        );

        $this->createIndex('idx-offers-price', 'offers', 'price');
        $this->createIndex('idx-offers-sku_status', 'offers', ['sku_id', 'status']); // Для фильтрации активных

        /**
         * Ускоряет выборку только активных предложений для отображения на сайте
         */
        $this->createIndex(
            'idx-offers-is_active',
            'offers',
            'status'
        );

        $this->createIndex('uidx_offers_vendor_sku',
            '{{%offers}}', ['vendor_id', 'sku_id'],
            true);

// Связь с таблицей товаров
//        $this->addForeignKey(
//            'fk_offers_sku',
//            '{{%offers}}',
//            'sku_id',
//            '{{%product_skus}}',
//            'id',
//            'RESTRICT',
//            'CASCADE'
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
        $this->dropIndex('uidx_offers_vendor_sku', 'offers');
        $this->dropIndex('idx-offers-is_active', 'offers');
        $this->dropIndex('idx-offers-vendor_id', 'offers');


        $this->dropTable('offers');
        $this->dropTable('{{%product_skus}}');
    }
}
