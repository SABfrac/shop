<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%feed_chunk_result}}`.
 */
class m251015_205804_create_feed_chunk_result_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {


        $this->createTable('{{%feed_chunk_result}}', [
            'id' => $this->primaryKey(),
            'report_id' => $this->integer()->notNull(),
            'processed_rows' => $this->integer()->notNull()->defaultValue(0),
            'errors_json' => $this->text(),
            'status' => $this->string(20)->notNull()->defaultValue('completed'),
            'duration_sec' => $this->decimal(10, 3)->null(),
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);

        $this->createIndex('idx_chunk_report', '{{%feed_chunk_result}}', 'report_id');

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {

        $this->dropIndex('idx_chunk_report', '{{%feed_chunk_result}}');
        $this->dropTable('{{%feed_chunk_result}}');
    }
}
