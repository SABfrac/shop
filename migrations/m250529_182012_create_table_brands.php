<?php

use yii\db\Migration;

class m250529_182012_create_table_brands extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Создаем таблицу брендов
        $this->createTable('brands', [
            'id' => $this->primaryKey(),
            'name' => $this->string(255)->notNull()->unique(),
            'description' => $this->text(), // Описание бренда
            'logo' => $this->string(255), // Путь к логотипу
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);

        // Добавляем столбец brand_id в таблицу products
        $this->addColumn('products', 'brand_id', $this->integer()->after('vendor_id'));

        // Создаем индекс для ускорения поиска
        $this->createIndex(
            'idx_products_brand_id',
            'products',
            'brand_id'
        );

        // Создаем внешний ключ
//        $this->addForeignKey(
//            'fk_products_brands',
//            'products',
//            'brand_id',
//            'brands',
//            'id',
//            'SET NULL', // или 'CASCADE' в зависимости от логики
//            'CASCADE'
//        );





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
    CREATE TRIGGER update_brands_updated_at
    BEFORE UPDATE ON brands
    FOR EACH ROW
        EXECUTE FUNCTION update_updated_at();
');

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('products', 'brand_id');
        $this->dropTable('brands');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250529_182012_create_table_brands cannot be reverted.\n";

        return false;
    }
    */
}
