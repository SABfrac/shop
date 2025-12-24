<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%fallback_product_buffer}}`.
 */
class m250722_222107_create_fallback_product_buffer_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('fallback_product_buffer', [
            'id' => $this->primaryKey(),
            'type' => $this->string(10)->notNull()->comment('Тип операции (insert/update)'),
            'payload' => $this->json()->notNull()->comment('Данные для обработки в формате JSON'),
            'created_at' => $this->integer()->notNull()->comment('Временная метка создания записи'),
        ]);


    }


    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('fallback_product_buffer');
    }
}
