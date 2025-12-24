<?php

use yii\db\Migration;

class m251114_230424_create_brand_name_index extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        // Создаём расширение pg_trgm, если оно ещё не существует
        $this->execute("CREATE EXTENSION IF NOT EXISTS pg_trgm;");

        // Создаём GIN индекс с использованием pg_trgm для поля name
        // Используем execute напрямую, так как Yii2 не поддерживает gin_trgm_ops в addIndex()
        $this->execute("CREATE INDEX CONCURRENTLY idx_brand_name_trgm ON brands USING gin (name gin_trgm_ops);");

    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        $this->execute("DROP INDEX CONCURRENTLY IF EXISTS idx_brand_name_trgm;");
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m251114_230424_create_brand_name_index cannot be reverted.\n";

        return false;
    }
    */
}
