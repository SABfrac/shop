<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%vendor_feed_reports}}`.
 */
class m251013_173659_create_vendor_feed_reports_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%vendor_feed_reports}}', [
            'id' => $this->primaryKey(),
            'vendor_id' => $this->integer()->notNull(),
            'total_rows' => $this->integer()->notNull()->defaultValue(0),
            'total_chunks' => $this->integer()->notNull()->defaultValue(0),
            'errors_json' => $this->text(), // JSON с ошибками по строкам
            'status' => $this->string(32)->notNull()->defaultValue('processing'), // processing, completed, completed_with_errors, failed
            'file_path' => $this->string(512),
             'started_at'=> $this->timestamp()->null(),
            'finished_at' => $this->timestamp()->null(),
            'total_duration_sec'=> $this->decimal(10, 3)->null()->defaultValue(0.000),
            'total_indexing_sec'=> $this->decimal(10, 3)->null()->defaultValue(0.000),
            'total_failed'=> $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),


        ]);

        $this->createIndex('idx_vendor_feed_reports_vendor_id', '{{%vendor_feed_reports}}', 'vendor_id');
//        $this->addForeignKey(
//            'fk_vendor_feed_reports_vendor_id',
//            '{{%vendor_feed_reports}}',
//            'vendor_id',
//            '{{%vendors}}',
//            'id',
//            'CASCADE',
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
        CREATE TRIGGER vendor_feed_reports_updated_at_trigger
        BEFORE UPDATE ON "vendor_feed_reports"
        FOR EACH ROW
        EXECUTE FUNCTION update_updated_at();
    ');

    }

    public function safeDown()
    {
//        $this->dropForeignKey('fk_vendor_feed_reports_vendor_id', '{{%vendor_feed_reports}}');
        $this->dropIndex('idx_vendor_feed_reports_vendor_id', '{{%vendor_feed_reports}}');
        $this->dropTable('{{%vendor_feed_reports}}');
    }
}
