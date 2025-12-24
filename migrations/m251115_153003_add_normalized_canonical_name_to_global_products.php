<?php

use yii\db\Migration;

class m251115_153003_add_normalized_canonical_name_to_global_products extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Добавляем столбец для нормализованного канонического имени
        $this->addColumn('{{%global_products}}', 'canonical_name_normalized', $this->string(512)->null());

        $rows = (new \yii\db\Query())
            ->select(['id', 'canonical_name'])
            ->from('{{%global_products}}')
            ->where(['is not', 'canonical_name', null])
            ->all();

        $command = $this->db->createCommand();
        foreach ($rows as $row) {
            $id = $row['id'];
            $canonicalName = $row['canonical_name'];

            // Нормализуем имя (без бренда, т.к. canonical_name уже "каноническое")
            $normalized = \app\helper\DataNormalizer::mathKeyNormalizer($canonicalName, 'Apple');

            if ($normalized !== null) {
                $command->update(
                    '{{%global_products}}',
                    ['canonical_name_normalized' => $normalized],
                    ['id' => $id]
                )->execute();
            }
        }


        // Создаём индекс на (category_id, canonical_name_normalized)
        $this->createIndex(
            'idx-global_products-category_normalized_name',
            '{{%global_products}}',
            ['category_id', 'canonical_name_normalized']
        );



    }

    public function safeDown()
    {
        $this->dropIndex('idx-global_products-category_normalized_name', '{{%global_products}}');
        $this->dropColumn('{{%global_products}}', 'canonical_name_normalized');
    }


    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m251115_153003_add_normalized_canonical_name_to_global_products cannot be reverted.\n";

        return false;
    }
    */
}
