<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%sku_attribute_values}}`.
 */
class m250904_221840_create_sku_attribute_values_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%sku_attribute_values}}', [
            'id' => $this->primaryKey(),
            'sku_id' => $this->integer()->notNull(),
            'attribute_id' => $this->integer()->notNull(),

            // Типизированные значения (как в product_attribute_values)
            'value_string' => $this->string()->null(),
            'value_int' => $this->integer()->null(),
            'value_float' => $this->double()->null(),
            'value_bool' => $this->boolean()->null(),
            'attribute_option_id' => $this->integer()->null(),

            'created_at' => $this->dateTime()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->dateTime()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);

        $this->createIndex('uidx_sku_attribute_values_sku_attr', '{{%sku_attribute_values}}', ['sku_id', 'attribute_id'], true);
        $this->createIndex('idx_sku_attribute_values_option', '{{%sku_attribute_values}}', 'attribute_option_id');
//


        $this->addForeignKey(
            'fk_sku_attribute_values_sku',
            '{{%sku_attribute_values}}',
            'sku_id',
            '{{%product_skus}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
//        $this->addForeignKey(
//            'fk_sku_attribute_values_attribute',
//            '{{%sku_attribute_values}}',
//            'attribute_id',
//            '{{%attributes}}',
//            'id',
//            'RESTRICT',
//            'CASCADE'
//        );
//        $this->addForeignKey(
//            'fk_sku_attribute_values_attribute_option',
//            '{{%sku_attribute_values}}',
//            'category_attribute_option_id',
//            '{{%category_attribute_options}}',
//            'id',
//            'RESTRICT',
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

        // Затем отдельно создаем триггер
        $this->execute('
        CREATE TRIGGER sku_attribute_values_updated_at_trigger
        BEFORE UPDATE ON "sku_attribute_values"
        FOR EACH ROW
        EXECUTE FUNCTION update_updated_at();
    ');

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropForeignKey('fk_sku_attribute_values_sku', '{{%sku_attribute_values}}');

        $this->dropIndex('idx_sku_attribute_values_option', '{{%sku_attribute_values}}');
        $this->dropIndex('uidx_sku_attribute_values_sku_attr', '{{%sku_attribute_values}}');

        $this->dropTable('{{%sku_attribute_values}}');
    }
}
