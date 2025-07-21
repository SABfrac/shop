<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%index}}`.
 */
class m250528_192016_create_index_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {

        $this->createIndex('idx-products-category', 'products', 'category_id');
        $this->createIndex('idx-products-status', 'products', 'status');


        // Индексы для EAV
        $this->createIndex('idx-product-attribute-values-product', 'product_attribute_values', 'product_id');
        $this->createIndex('idx-product-attribute-values-attribute', 'product_attribute_values', 'attribute_id');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {


        $this->dropIndex('idx-product-attribute-values-attribute', 'product_attribute_values');
        $this->dropIndex('idx-product-attribute-values-product', 'product_attribute_values');


        $this->dropIndex('idx-products-status', 'products');
        $this->dropIndex('idx-products-category', 'products');



    }
}
