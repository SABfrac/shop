<?php

use yii\db\Migration;

class m250528_124517_create_table_vendors_products extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('vendors', [
            'id' => $this->primaryKey(),
            'company_name' => $this->string()->notNull(),
            'email' => $this->string()->notNull()->unique(),
            'status' => $this->smallInteger()->defaultValue(10),
            'balance' => $this->decimal(15, 2)->defaultValue(0),
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);

        $this->createIndex('idx-vendors-status', 'vendors', 'status');
        $this->createIndex('idx-vendors-company_name', 'vendors', 'company_name');

        // Таблица товаров
        $this->createTable('products', [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'category_id' => $this->integer()->notNull(),
            'description' => $this->text(),
            'brand_id'=> $this->integer()->null(),
            'slug' => $this->string(),
            'status' => $this->smallInteger()->defaultValue(10),

            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);

        // Индексы для ускорения поиска

        $this->createIndex('idx-products-brand_id', 'products', 'brand_id');




        $this->execute('
        CREATE OR REPLACE FUNCTION update_updated_at()
        RETURNS TRIGGER AS $$
        BEGIN
            NEW.updated_at = NOW();
            RETURN NEW;
        END;
        $$ LANGUAGE plpgsql;
    ');

        $this->execute('
    CREATE TRIGGER update_products_updated_at
    BEFORE UPDATE ON products
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();
');
        $this->execute('
    CREATE TRIGGER update_vendors_updated_at
    BEFORE UPDATE ON vendors
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();
');


////         Создаем внешний ключ
//        $this->addForeignKey(
//            'fk_products_brands',
//            'products',
//            'brand_id',
//            'brands',
//            'id',
//            'SET NULL', // или 'CASCADE' в зависимости от логики
//            'CASCADE'
//        );


    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
//        $this->dropForeignKey('fk_products_brands', 'products');
        $this->dropTable('products');
        $this->dropTable('vendors');

    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250528_124517_create_table_vendors_products cannot be reverted.\n";

        return false;
    }
    */
}
