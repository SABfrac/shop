<?php

use yii\db\Migration;

class m250528_124517_create_table_vendors_products extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('vendors', [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'email' => $this->string()->notNull()->unique(),
            'passport' => $this->string()->notNull()->unique(),
            'password_hash' => $this->string()->notNull(),
            'email_confirm_token' => $this->string(),
            'status' => $this->smallInteger()->defaultValue(10),
            'balance' => $this->decimal(15, 2)->defaultValue(0),
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);

        $this->createIndex('idx-vendors-status', 'vendors', 'status');
        $this->createIndex('idx-vendor-email', 'vendors', 'email');

        // Таблица товаров
        $this->createTable('global_products', [
            'id' => $this->primaryKey(),
            'canonical_name' => 'citext NOT NULL',
            'model_number' => 'citext  NULL',
            'category_id' => $this->integer()->notNull(),
            'description' => $this->text(),
            'brand_id'=> $this->integer()->null(),
            'slug' => $this->string(),
            'gtin' => $this->string(32)->unique()->null()->comment('GTIN (EAN/UPC/ISBN)'),
            'status' => $this->smallInteger()->defaultValue(10),
            'match_key' => $this->string(512)->notNull()->unique(),
            'attributes_json' => $this->json()->null()->defaultValue('{}'),

            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);

        $iphoneModels = [
            // iPhone 15 Series (2023)
            ['Apple iPhone 15', 'iPhone16,1', 'iPhone 15 с Dynamic Island и 48 МП камерой.', 'apple-iphone-15', '1234567890001', 2023, 6.1, 'OLED', 'A16 Bionic'],
            ['Apple iPhone 15 Plus', 'iPhone16,2', 'iPhone 15 Plus с большим экраном.', 'apple-iphone-15-plus', '1234567890002', 2023, 6.7, 'OLED', 'A16 Bionic'],
            ['Apple iPhone 15 Pro', 'iPhone16,3', 'iPhone 15 Pro с титановым корпусом и A17 Pro.', 'apple-iphone-15-pro', '1234567890003', 2023, 6.1, 'OLED', 'A17 Pro'],
            ['Apple iPhone 15 Pro Max', 'iPhone16,4', 'iPhone 15 Pro Max с перископическим зумом.', 'apple-iphone-15-pro-max', '1234567890004', 2023, 6.7, 'OLED', 'A17 Pro'],

            // iPhone 14 Series (2022)
            ['Apple iPhone 14', 'iPhone14,7', 'iPhone 14 с улучшенной камерой и аварийным SOS.', 'apple-iphone-14', '1234567890005', 2022, 6.1, 'OLED', 'A15 Bionic'],
            ['Apple iPhone 14 Plus', 'iPhone14,8', 'iPhone 14 Plus с увеличенным экраном и батареей.', 'apple-iphone-14-plus', '1234567890006', 2022, 6.7, 'OLED', 'A15 Bionic'],
            ['Apple iPhone 14 Pro', 'iPhone15,2', 'iPhone 14 Pro с Dynamic Island и Always-On дисплеем.', 'apple-iphone-14-pro', '1234567890007', 2022, 6.1, 'OLED', 'A16 Bionic'],
            ['Apple iPhone 14 Pro Max', 'iPhone15,3', 'iPhone 14 Pro Max с увеличенным экраном.', 'apple-iphone-14-pro-max', '1234567890008', 2022, 6.7, 'OLED', 'A16 Bionic'],

            // iPhone 13 Series (2021)
            ['Apple iPhone 13 mini', 'iPhone14,4', 'Компактный iPhone 13 с мощной камерой.', 'apple-iphone-13-mini', '1234567890009', 2021, 5.4, 'OLED', 'A15 Bionic'],
            ['Apple iPhone 13', 'iPhone14,5', 'iPhone 13 — баланс между размером и функциями.', 'apple-iphone-13', '1234567890010', 2021, 6.1, 'OLED', 'A15 Bionic'],
            ['Apple iPhone 13 Pro', 'iPhone14,2', 'iPhone 13 Pro с ProMotion и 3-камерной системой.', 'apple-iphone-13-pro', '1234567890011', 2021, 6.1, 'OLED', 'A15 Bionic'],
            ['Apple iPhone 13 Pro Max', 'iPhone14,3', 'iPhone 13 Pro Max с увеличенной батареей.', 'apple-iphone-13-pro-max', '1234567890012', 2021, 6.7, 'OLED', 'A15 Bionic'],

            // iPhone SE (3rd gen, 2022)
            ['Apple iPhone SE (3rd generation)', 'iPhone14,6', 'Компактный iPhone с процессором A15 Bionic.', 'apple-iphone-se-3rd-gen', '1234567890013', 2022, 4.7, 'LCD', 'A15 Bionic'],

            // iPhone 12 Series (2020)
            ['Apple iPhone 12 mini', 'iPhone13,1', 'iPhone 12 mini — самый маленький 5G iPhone.', 'apple-iphone-12-mini', '1234567890014', 2020, 5.4, 'OLED', 'A14 Bionic'],
            ['Apple iPhone 12', 'iPhone13,2', 'iPhone 12 с Ceramic Shield и 5G.', 'apple-iphone-12', '1234567890015', 2020, 6.1, 'OLED', 'A14 Bionic'],
            ['Apple iPhone 12 Pro', 'iPhone13,3', 'iPhone 12 Pro с LiDAR и Pro камеры.', 'apple-iphone-12-pro', '1234567890016', 2020, 6.1, 'OLED', 'A14 Bionic'],
            ['Apple iPhone 12 Pro Max', 'iPhone13,4', 'iPhone 12 Pro Max с крупнейшей камерой.', 'apple-iphone-12-pro-max', '1234567890017', 2020, 6.7, 'OLED', 'A14 Bionic'],

            // iPhone SE (2nd gen, 2020)
            ['Apple iPhone SE (2nd generation)', 'iPhone12,8', 'iPhone SE второго поколения с Touch ID и A13.', 'apple-iphone-se-2nd-gen', '1234567890018', 2020, 4.7, 'LCD', 'A13 Bionic'],

            // iPhone 11 Series (2019)
            ['Apple iPhone 11', 'iPhone12,1', 'iPhone 11 с двойной камерой и Liquid Retina.', 'apple-iphone-11', '1234567890019', 2019, 6.1, 'LCD', 'A13 Bionic'],
            ['Apple iPhone 11 Pro', 'iPhone12,3', 'iPhone 11 Pro с тройной камерой и OLED.', 'apple-iphone-11-pro', '1234567890020', 2019, 5.8, 'OLED', 'A13 Bionic'],
            ['Apple iPhone 11 Pro Max', 'iPhone12,5', 'iPhone 11 Pro Max с увеличенным экраном.', 'apple-iphone-11-pro-max', '1234567890021', 2019, 6.5, 'OLED', 'A13 Bionic'],

            // iPhone XR, XS, XS Max (2018)
            ['Apple iPhone XR', 'iPhone11,8', 'iPhone XR с Liquid Retina и единственной камерой.', 'apple-iphone-xr', '1234567890022', 2018, 6.1, 'LCD', 'A12 Bionic'],
            ['Apple iPhone XS', 'iPhone11,2', 'iPhone XS с OLED и двойной камерой.', 'apple-iphone-xs', '1234567890023', 2018, 5.8, 'OLED', 'A12 Bionic'],
            ['Apple iPhone XS Max', 'iPhone11,4', 'iPhone XS Max с увеличенным OLED экраном.', 'apple-iphone-xs-max', '1234567890024', 2018, 6.5, 'OLED', 'A12 Bionic'],

            // iPhone X, 8, 8 Plus (2017)
            ['Apple iPhone X', 'iPhone10,3', 'iPhone X с Face ID и OLED без рамок.', 'apple-iphone-x', '1234567890025', 2017, 5.8, 'OLED', 'A11 Bionic'],
            ['Apple iPhone 8', 'iPhone10,1', 'iPhone 8 с алюминиевым корпусом и беспроводной зарядкой.', 'apple-iphone-8', '1234567890026', 2017, 4.7, 'LCD', 'A11 Bionic'],
            ['Apple iPhone 8 Plus', 'iPhone10,2', 'iPhone 8 Plus с двойной камерой.', 'apple-iphone-8-plus', '1234567890027', 2017, 5.5, 'LCD', 'A11 Bionic'],

            // iPhone 7, 7 Plus (2016)
            ['Apple iPhone 7', 'iPhone9,1', 'iPhone 7 с водонепроницаемостью и одним разъемом.', 'apple-iphone-7', '1234567890028', 2016, 4.7, 'LCD', 'A10 Fusion'],
            ['Apple iPhone 7 Plus', 'iPhone9,2', 'iPhone 7 Plus с двойной камерой и Portrait Mode.', 'apple-iphone-7-plus', '1234567890029', 2016, 5.5, 'LCD', 'A10 Fusion'],

            // iPhone 6s, 6s Plus (2015)
            ['Apple iPhone 6s', 'iPhone8,1', 'iPhone 6s с 3D Touch и улучшенной камерой.', 'apple-iphone-6s', '1234567890030', 2015, 4.7, 'LCD', 'A9'],
            ['Apple iPhone 6s Plus', 'iPhone8,2', 'iPhone 6s Plus с двойной камерой и оптической стабилизацией.', 'apple-iphone-6s-plus', '1234567890031', 2015, 5.5, 'LCD', 'A9'],
        ];

        $data = [];
        foreach ($iphoneModels as $i => $model) {
            [$name, $modelNum, $desc, $slug, $gtin, $year, $screen, $tech, $chip] = $model;
            $data[] = [
                'canonical_name' => $name,
                'model_number' => $modelNum,
                'category_id' => 11,
                'description' => $desc,
                'brand_id' => 1, // Apple
                'slug' => $slug,
                'gtin' => $gtin,
                'status' => 10,
                'match_key' => 'apple|' . mb_strtolower( $name, 'UTF-8') . '|11',
                'attributes_json' => json_encode([
                    'model' => $name,
                    'brand' => 'Apple',
                    'screen_size_in' => $screen,
                    'screen_technology' => $tech,
                    'processor' => $chip,
                    'os' => 'iOS',
                    'year' => $year,
                ], JSON_UNESCAPED_UNICODE),
            ];
        }

        $this->batchInsert('global_products', [
            'canonical_name',
            'model_number',
            'category_id',
            'description',
            'brand_id',
            'slug',
            'gtin',
            'status',
            'match_key',
            'attributes_json',
        ], $data);




        // Индексы для ускорения поиска
        $this->createIndex('uidx-global_products-match_key', 'global_products', 'match_key', true);
        $this->createIndex('idx-global_products-gtin', 'global_products', ['gtin'], true);
        $this->createIndex('idx-global_products-brand_id', 'global_products', 'brand_id');
        $this->createIndex('idx-global_products-category_id', 'global_products', 'category_id');
        $this->createIndex('idx-global_products-brand_model', 'global_products', ['brand_id', 'model_number']);
        $this->createIndex('idx-global_products-canonical_name', 'global_products', ['canonical_name']);




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
    CREATE TRIGGER update_global_products_updated_at
    BEFORE UPDATE ON global_products
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();
');
        $this->execute('
    CREATE TRIGGER update_vendors_updated_at
    BEFORE UPDATE ON vendors
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();
');


////         Создаем внешний ключ
//        $this->addForeignKey(
//            'fk_global_products_brands',
//            'global_products',
//            'brand_id',
//            'brands',
//            'id',
//            'SET NULL', // или 'CASCADE' в зависимости от логики
//            'CASCADE'
//        );


    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
//        $this->dropForeignKey('fk_global_products_brands', 'global_products');
        $this->dropTable('global_products');
        $this->dropTable('vendors');

    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250528_124517_create_table_vendors_products cannot be reverted.\n";

        return false;
    }
    */
}
