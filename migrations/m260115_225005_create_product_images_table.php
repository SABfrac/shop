<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%product_images}}`.
 */
class m260115_225005_create_product_images_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%product_images}}', [
            'id' => $this->primaryKey(),
            'entity_type' => $this->string(50)->notNull()->comment('global_product, offer (or review)'),
            'entity_id' => $this->integer()->notNull(),
            'storage_path' => $this->string(255)->notNull(),
            'filename' => $this->string(255),
            'is_main' => $this->boolean()->defaultValue(false),
            'sort_order' => $this->integer()->defaultValue(0),
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);

        $this->createIndex('idx-entity-type-id', '{{%product_images}}', ['entity_type', 'entity_id']);
        $this->createIndex('idx-is-main', '{{%product_images}}', 'is_main');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%product_images}}');
    }
}
