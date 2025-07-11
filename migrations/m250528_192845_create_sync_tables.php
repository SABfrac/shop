<?php

use yii\db\Migration;

class m250528_192845_create_sync_tables extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Таблица для отслеживания синхронизации
        $this->createTable('sync_queue', [
            'id' => $this->primaryKey(),
            'entity_type' => $this->string()->notNull(),
            'entity_id' => $this->integer()->notNull(),
            'operation' => $this->string()->notNull(),
            'status' => $this->smallInteger()->defaultValue(0),
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
            'processed_at' => $this->timestamp(),
        ]);

        $this->createIndex('idx-sync-queue-status', 'sync_queue', 'status');
        $this->createIndex('idx-sync-queue-entity', 'sync_queue', ['entity_type', 'entity_id']);

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropIndex('idx-sync-queue-entity', 'sync_queue');
        $this->dropIndex('idx-sync-queue-status', 'sync_queue');
        $this->dropTable('sync_queue');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250528_192845_create_sync_tables cannot be reverted.\n";

        return false;
    }
    */
}
