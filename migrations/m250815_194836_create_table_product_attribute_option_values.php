<?php

use yii\db\Migration;

class m250815_194836_create_table_product_attribute_option_values extends Migration
{
    /**
     * для выбора нескольких одновременно опций товара
     * например для выбора процессора поддерживаемую оперативная память может быть DDR4 и DDR5
     */
    public function safeUp()
    {


        $this->createTable('product_attribute_option_values', [
            'global_product_id' => $this->bigInteger()->notNull(),
            'attribute_id' => $this->bigInteger()->notNull(),
            'category_attribute_option_id' => $this->bigInteger()->notNull(),
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);

        $this->addPrimaryKey('pk_pao',
            'product_attribute_option_values',
            ['global_product_id', 'attribute_id', 'category_attribute_option_id']);


//        $this->addForeignKey(
//            'fk_pao_product',
//            'product_attribute_option_values',
//            'product_id',
//            'products',
//            'id',
//            'CASCADE',
//            'NO ACTION');
//        $this->addForeignKey('fk_pao_attribute',
//            'product_attribute_option_values',
//            'attribute_id',
//            'attributes',
//            'id',
//            'CASCADE',
//            'NO ACTION');
//        $this->addForeignKey('fk_pao_attr_opt',
//            'product_attribute_option_values', ['attribute_id', 'attribute_option_id'],
//            'attributes_option', ['attribute_id', 'id'],
//            'CASCADE', 'NO ACTION'
//        );
        // 3) Индексы
        $this->createIndex('idx_pao_attr_opt_prod',
            'product_attribute_option_values', ['attribute_id', 'category_attribute_option_id', 'global_product_id']);
        $this->createIndex('idx_pao_prod',
            'product_attribute_option_values', ['global_product_id']);


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
    CREATE TRIGGER update_product_attribute_option_values_updated_at
    BEFORE UPDATE ON product_attribute_option_values
    FOR EACH ROW
        EXECUTE FUNCTION update_updated_at_column();
        ');


    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
//        $this->dropForeignKey('fk_pao_attr_opt', 'product_attribute_option_values');
//        $this->dropForeignKey('fk_pao_attribute', 'product_attribute_option_values');
//        $this->dropForeignKey('fk_pao_product', 'product_attribute_option_values');
        $this->dropTable('product_attribute_option_values');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250815_194836_create_table_product_attribute_option_values cannot be reverted.\n";

        return false;
    }
    */
}
