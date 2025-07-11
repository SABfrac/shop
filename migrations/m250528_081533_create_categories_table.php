<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%categories}}`.
 */
class m250528_081533_create_categories_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%categories}}', [
            'id' => $this->primaryKey(),
            'parent_id' => $this->integer()->null(),
            'name' => $this->string(255)->notNull(),
            'slug' => $this->string(255)->notNull()->unique(),
            'description' => $this->text()->null(),
            'image' => $this->string(255)->null(),
            'sort_order' => $this->integer()->defaultValue(0),
            'status' => $this->smallInteger()->defaultValue(1),
            'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP')
        ] );

        // Внешний ключ для parent_id
        $this->addForeignKey(
            'fk_categories_parent_id',
            '{{%categories}}',
            'parent_id',
            '{{%categories}}',
            'id',
            'SET NULL',
            'CASCADE'
        );
        $currentTime = new \yii\db\Expression('NOW()');
        // Вставляем основные категории (родительские)
        $this->batchInsert('{{%categories}}',
            ['parent_id', 'name', 'slug', 'description', 'sort_order', 'created_at', 'updated_at'],
            [
                [null, 'Электроника', 'electronics', 'Все виды электроники', 1000, $currentTime, $currentTime],
                [null, 'Одежда', 'clothing', 'Мужская, женская и детская одежда', 2000, $currentTime, $currentTime],
                [null, 'Бытовая техника', 'appliances', 'Крупная и мелкая бытовая техника', 3000, $currentTime, $currentTime],
                [null, 'Дом и сад', 'home-garden', 'Товары для дома и сада', 4000, $currentTime, $currentTime],
                [null, 'Красота и здоровье', 'beauty-health', 'Косметика и товары для здоровья', 5000, $currentTime, $currentTime],
                [null, 'Спорт и отдых', 'sports-leisure', 'Спортивные товары и товары для отдыха', 6000, $currentTime, $currentTime],
                [null, 'Детские товары', 'kids', 'Товары для детей', 7000, $currentTime, $currentTime],
                [null, 'Продукты питания', 'food', 'Продукты питания и напитки', 8000, $currentTime, $currentTime],
                [null, 'Бытовая химия', 'household-chemicals', 'Чистящие и моющие средства', 9000, $currentTime, $currentTime],
                [null, 'Автотовары', 'auto', 'Автомобильные товары и аксессуары', 10000, $currentTime, $currentTime]
            ]
        );
        // Получаем ID основных категорий
        $electronicsId = $this->getDb()->createCommand("SELECT id FROM {{%categories}} WHERE slug = 'electronics'")->queryScalar();
        $clothingId = $this->getDb()->createCommand("SELECT id FROM {{%categories}} WHERE slug = 'clothing'")->queryScalar();
        $appliancesId = $this->getDb()->createCommand("SELECT id FROM {{%categories}} WHERE slug = 'appliances'")->queryScalar();
        $homeGardenId = $this->getDb()->createCommand("SELECT id FROM {{%categories}} WHERE slug = 'home-garden'")->queryScalar();
        $beautyHealthId = $this->getDb()->createCommand("SELECT id FROM {{%categories}} WHERE slug = 'beauty-health'")->queryScalar();
        $sportsLeisureId = $this->getDb()->createCommand("SELECT id FROM {{%categories}} WHERE slug = 'sports-leisure'")->queryScalar();
        $kidsId = $this->getDb()->createCommand("SELECT id FROM {{%categories}} WHERE slug = 'kids'")->queryScalar();
        $foodId = $this->getDb()->createCommand("SELECT id FROM {{%categories}} WHERE slug = 'food'")->queryScalar();
        $chemicalsId = $this->getDb()->createCommand("SELECT id FROM {{%categories}} WHERE slug = 'household-chemicals'")->queryScalar();
        $autoId = $this->getDb()->createCommand("SELECT id FROM {{%categories}} WHERE slug = 'auto'")->queryScalar();


// Вставляем подкатегории для Электроники
        $this->batchInsert('{{%categories}}',
            ['parent_id', 'name', 'slug', 'description', 'sort_order', 'created_at', 'updated_at'],
            [
                [$electronicsId, 'Смартфоны', 'smartphones', 'Смартфоны и мобильные телефоны', 1100, $currentTime, $currentTime],
                [$electronicsId, 'Ноутбуки', 'laptops', 'Ноутбуки и ультрабуки', 1200, $currentTime, $currentTime],
                [$electronicsId, 'Компьютерные комплектующие', 'computer-parts', 'Процессоры, видеокарты, материнские платы и другие комплектующие', 1300, $currentTime, $currentTime],
                [$electronicsId, 'Телевизоры', 'tvs', 'Телевизоры и мониторы', 1400, $currentTime, $currentTime],
                [$electronicsId, 'Фототехника', 'photo', 'Фотоаппараты и объективы', 1500, $currentTime, $currentTime],
                [$electronicsId, 'Аудиотехника', 'audio', 'Наушники, колонки и аудиосистемы', 1600, $currentTime, $currentTime],
                [$electronicsId, 'Игровые консоли', 'gaming', 'Игровые приставки и аксессуары', 1700, $currentTime, $currentTime],
            ]
        );

       // Получаем ID новой категории "Компьютерные комплектующие"
$computerPartsId = $this->getDb()->createCommand("SELECT id FROM {{%categories}} WHERE slug = 'computer-parts'")->queryScalar();

// Вставляем подкатегории для Компьютерных комплектующих
$this->batchInsert('{{%categories}}',
    ['parent_id', 'name', 'slug', 'description', 'sort_order', 'created_at', 'updated_at'],
    [
        [$computerPartsId, 'Процессоры', 'processors', 'Центральные процессоры (CPU)', 1310, $currentTime, $currentTime],
        [$computerPartsId, 'Видеокарты', 'graphic-cards', 'Графические процессоры (GPU)', 1320, $currentTime, $currentTime],
        [$computerPartsId, 'Материнские платы', 'motherboards', 'Системные платы для ПК', 1330, $currentTime, $currentTime],
        [$computerPartsId, 'Оперативная память', 'ram', 'Модули памяти RAM', 1340, $currentTime, $currentTime],
        [$computerPartsId, 'Накопители', 'storage', 'SSD, HDD и другие накопители', 1350, $currentTime, $currentTime],
        [$computerPartsId, 'Блоки питания', 'power-supplies', 'Блоки питания для ПК', 1360, $currentTime, $currentTime],
        [$computerPartsId, 'Корпуса', 'computer-cases', 'Корпуса для системных блоков', 1370, $currentTime, $currentTime],
        [$computerPartsId, 'Охлаждение', 'cooling', 'Системы охлаждения для ПК', 1380, $currentTime, $currentTime],
    ]
);


        // Вставляем подкатегории для Одежды
        $this->batchInsert('{{%categories}}',
            ['parent_id', 'name', 'slug', 'description', 'sort_order', 'created_at', 'updated_at'],
            [
                [$clothingId, 'Мужская одежда', 'mens-clothing', 'Одежда для мужчин', 2100, $currentTime, $currentTime],
                [$clothingId, 'Женская одежда', 'womens-clothing', 'Одежда для женщин', 2200, $currentTime, $currentTime],
                [$clothingId, 'Детская одежда', 'kids-clothing', 'Одежда для детей', 2300, $currentTime, $currentTime],
                [$clothingId, 'Обувь', 'footwear', 'Мужская, женская и детская обувь', 2400, $currentTime, $currentTime],
                [$clothingId, 'Аксессуары', 'accessories', 'Сумки, ремни, перчатки и другие аксессуары', 2500, $currentTime, $currentTime],
            ]
        );

        // Получаем ID созданных категорий
        $mensClothingId = $this->getDb()->createCommand("SELECT id FROM {{%categories}} WHERE slug = 'mens-clothing'")->queryScalar();
        $womensClothingId = $this->getDb()->createCommand("SELECT id FROM {{%categories}} WHERE slug = 'womens-clothing'")->queryScalar();
        $kidsClothingId = $this->getDb()->createCommand("SELECT id FROM {{%categories}} WHERE slug = 'kids-clothing'")->queryScalar();
        $footwearId = $this->getDb()->createCommand("SELECT id FROM {{%categories}} WHERE slug = 'footwear'")->queryScalar();
        $accessoriesId = $this->getDb()->createCommand("SELECT id FROM {{%categories}} WHERE slug = 'accessories'")->queryScalar();


        // Вставляем подкатегории для Одежды
        $this->batchInsert('{{%categories}}',
            ['parent_id', 'name', 'slug', 'description', 'sort_order', 'created_at', 'updated_at'],
            [
                // Подкатегории для мужской одежды
                [$mensClothingId, 'Рубашки', 'mens-shirts', 'Мужские рубашки', 2110, $currentTime, $currentTime],
                [$mensClothingId, 'Футболки', 'mens-t-shirts', 'Мужские футболки', 2120, $currentTime, $currentTime],
                [$mensClothingId, 'Джинсы', 'mens-jeans', 'Мужские джинсы', 2130, $currentTime, $currentTime],
                [$mensClothingId, 'Брюки', 'mens-pants', 'Мужские брюки', 2140, $currentTime, $currentTime],
                [$mensClothingId, 'Костюмы', 'mens-suits', 'Мужские костюмы', 2150, $currentTime, $currentTime],
                [$mensClothingId, 'Худи и толстовки', 'mens-hoodies', 'Мужские худи и толстовки', 2160, $currentTime, $currentTime],
                [$mensClothingId, 'Верхняя одежда', 'mens-outerwear', 'Пальто, куртки, пуховики', 2170, $currentTime, $currentTime],
                [$mensClothingId, 'Нижнее белье', 'mens-underwear', 'Трусы, плавки, майки', 2180, $currentTime, $currentTime],


                // Подкатегории для женской одежды
                [$womensClothingId, 'Платья', 'womens-dresses', 'Женские платья', 2210, $currentTime, $currentTime],
                [$womensClothingId, 'Блузки', 'womens-blouses', 'Женские блузки', 2220, $currentTime, $currentTime],
                [$womensClothingId, 'Футболки', 'womens-t-shirts', 'Женские футболки', 2230, $currentTime, $currentTime],
                [$womensClothingId, 'Джинсы', 'womens-jeans', 'Женские джинсы', 2240, $currentTime, $currentTime],
                [$womensClothingId, 'Юбки', 'womens-skirts', 'Женские юбки', 2250, $currentTime, $currentTime],
                [$womensClothingId, 'Брюки', 'womens-pants', 'Женские брюки', 2260, $currentTime, $currentTime],
                [$womensClothingId, 'Худи и толстовки', 'womens-hoodies', 'Женские худи и толстовки', 2270, $currentTime, $currentTime],
                [$womensClothingId, 'Верхняя одежда', 'womens-outerwear', 'Пальто, куртки, пуховики', 2280, $currentTime, $currentTime],
                [$womensClothingId, 'Женское нижнее белье', 'women-underwear', 'Нижнее белье для женщин', 2290, $currentTime, $currentTime],
            ]
        );

                $mensUnderwearId=$this->getDb()->createCommand("SELECT id FROM {{%categories}} WHERE slug = 'mens-underwear'")->queryScalar();
                $womensLingerieId=$this->getDb()->createCommand("SELECT id FROM {{%categories}} WHERE slug = 'women-underwear'")->queryScalar();

        $this->batchInsert('{{%categories}}',
            ['parent_id', 'name', 'slug', 'description', 'sort_order', 'created_at', 'updated_at'],
            [

                // Подкатегории для мужского нижнего белья
                [$mensUnderwearId, 'Трусы', 'mens-briefs', 'Мужские трусы', 2181, $currentTime, $currentTime],
                [$mensUnderwearId, 'Плавки', 'mens-swimwear', 'Плавки и боксеры для плавания', 2182, $currentTime, $currentTime],
                [$mensUnderwearId, 'Боксеры', 'mens-boxers', 'Боксеры и семейные трусы', 2183, $currentTime, $currentTime],
                [$mensUnderwearId, 'Майки', 'mens-undershirts', 'Нательные майки и фуфайки', 2184, $currentTime, $currentTime],
                [$mensUnderwearId, 'Термобелье', 'mens-thermo', 'Термобелье для холодного сезона', 2185, $currentTime, $currentTime],
                [$mensUnderwearId, 'Домашняя одежда', 'mens-loungewear', 'Домашние костюмы и пижамы', 2186, $currentTime, $currentTime],
                [$mensUnderwearId, 'Носки', 'mens-socks', 'Мужские носки', 2187, $currentTime, $currentTime],
                // Подкатегории для женского нижнего белья
                [$womensLingerieId, 'Бюстгальтеры', 'bras', 'Женские бюстгальтеры', 2291, $currentTime, $currentTime],
                [$womensLingerieId, 'Трусы', 'panties', 'Женские трусы', 2292, $currentTime, $currentTime],
                [$womensLingerieId, 'Комплекты', 'lingerie-sets', 'Комплекты нижнего белья', 2293, $currentTime, $currentTime],
                [$womensLingerieId, 'Корсеты', 'corsets', 'Корсеты и утягивающее белье', 2294, $currentTime, $currentTime],
                [$womensLingerieId, 'Чулки и колготки', 'hosiery', 'Чулки, колготки, носочки', 2295, $currentTime, $currentTime],
                [$womensLingerieId, 'Домашняя одежда', 'loungewear', 'Пижамы, халаты', 2296, $currentTime, $currentTime],
                [$womensLingerieId, 'Купальники', 'swimwear', 'Купальники и пляжное белье', 2297, $currentTime, $currentTime],


                // Подкатегории для детской одежды
                [$kidsClothingId, 'Одежда для мальчиков', 'boys-clothing', 'Одежда для мальчиков', 2310, $currentTime, $currentTime],
                [$kidsClothingId, 'Одежда для девочек', 'girls-clothing', 'Одежда для девочек', 2320, $currentTime, $currentTime],
                [$kidsClothingId, 'Одежда для малышей', 'baby-clothing', 'Одежда для новорожденных', 2330, $currentTime, $currentTime],
                [$kidsClothingId, 'Школьная форма', 'school-uniforms', 'Школьная форма для детей', 2340, $currentTime, $currentTime],


                // Подкатегории для обуви
                [$footwearId, 'Мужская обувь', 'mens-footwear', 'Обувь для мужчин', 2410, $currentTime, $currentTime],
                [$footwearId, 'Женская обувь', 'womens-footwear', 'Обувь для женщин', 2420, $currentTime, $currentTime],
                [$footwearId, 'Детская обувь', 'kids-footwear', 'Обувь для детей', 2430, $currentTime, $currentTime],
                [$footwearId, 'Спортивная обувь', 'sports-shoes', 'Кроссовки и спортивная обувь', 2440, $currentTime, $currentTime],
                [$footwearId, 'Сапоги и ботинки', 'boots', 'Зимняя и демисезонная обувь', 2450, $currentTime, $currentTime],


                // Подкатегории для аксессуаров
                [$accessoriesId, 'Сумки', 'bags', 'Сумки, рюкзаки, кошельки', 2510, $currentTime, $currentTime],
                [$accessoriesId, 'Головные уборы', 'hats', 'Шапки, кепки, шляпы', 2520, $currentTime, $currentTime],
                [$accessoriesId, 'Ремни и пояса', 'belts', 'Ремни для мужчин и женщин', 2530, $currentTime, $currentTime],
                [$accessoriesId, 'Перчатки и варежки', 'gloves', 'Перчатки и варежки', 2540, $currentTime, $currentTime],
                [$accessoriesId, 'Шарфы и платки', 'scarves', 'Шарфы, палантины, платки', 2550, $currentTime, $currentTime],


            ]
        );




// Вставляем подкатегории для Бытовая техника
        $this->batchInsert('{{%categories}}',
            ['parent_id', 'name', 'slug', 'description', 'sort_order', 'created_at', 'updated_at'],
            [
                [$appliancesId, 'Крупная бытовая техника', 'major-appliances', 'Холодильники, стиральные машины, плиты', 3100, $currentTime, $currentTime],
                [$appliancesId, 'Мелкая бытовая техника', 'small-appliances', 'Чайники, блендеры, утюги', 3200, $currentTime, $currentTime],
                [$appliancesId, 'Климатическая техника', 'climate-control', 'Кондиционеры, обогреватели, вентиляторы', 3300, $currentTime, $currentTime],
                [$appliancesId, 'Техника для кухни', 'kitchen-appliances', 'Микроволновки, мультиварки, кофеварки', 3400, $currentTime, $currentTime],
                [$appliancesId, 'Техника для дома', 'home-appliances', 'Пылесосы, пароочистители, швейные машины', 3500, $currentTime, $currentTime],
                [$appliancesId, 'Техника для красоты', 'beauty-appliances', 'Фены, эпиляторы, триммеры', 3600, $currentTime, $currentTime],

            ]
        );

        // Получаем ID категории "Крупная бытовая техника"
        $majorAppliancesId = $this->getDb()->createCommand("SELECT id FROM {{%categories}} WHERE slug = 'major-appliances'")->queryScalar();

// Обновляем подкатегории для "Крупная бытовая техника"
        $this->batchInsert('{{%categories}}',
            ['parent_id', 'name', 'slug', 'description', 'sort_order', 'created_at', 'updated_at'],
            [
                [$majorAppliancesId, 'Холодильники', 'refrigerators', 'Однокамерные, двухкамерные, side-by-side', 3110, $currentTime, $currentTime],
                [$majorAppliancesId, 'Морозильные камеры', 'freezers', 'Горизонтальные и вертикальные морозилки', 3120, $currentTime, $currentTime],
                [$majorAppliancesId, 'Стиральные машины', 'washing-machines', 'Фронтальные и вертикальные загрузки', 3130, $currentTime, $currentTime],
                [$majorAppliancesId, 'Сушильные машины', 'dryers', 'Сушилки для белья', 3140, $currentTime, $currentTime],
                [$majorAppliancesId, 'Посудомоечные машины', 'dishwashers', 'Полноразмерные и компактные модели', 3150, $currentTime, $currentTime],
                [$majorAppliancesId, 'Плиты и печи', 'stoves-ovens', 'Газовые, электрические, комбинированные', 3160, $currentTime, $currentTime],
                [$majorAppliancesId, 'Вытяжки', 'kitchen-hoods', 'Купольные, встраиваемые, островные', 3170, $currentTime, $currentTime],
                [$majorAppliancesId, 'Комплекты техники', 'appliance-sets', 'Наборы техники в одном стиле', 3180, $currentTime, $currentTime],
            ]
        );



        // Получаем ID категории "Техника для кухни"
        $kitchenAppliancesId = $this->getDb()->createCommand("SELECT id FROM {{%categories}} WHERE slug = 'kitchen-appliances'")->queryScalar();

// Обновляем подкатегории для "Техника для кухни"
        $this->batchInsert('{{%categories}}',
            ['parent_id', 'name', 'slug', 'description', 'sort_order', 'created_at', 'updated_at'],
            [
                [$kitchenAppliancesId, 'Микроволновые печи', 'microwaves', 'Микроволновки различных мощностей и объемов', 3410, $currentTime, $currentTime],
                [$kitchenAppliancesId, 'Мультиварки', 'multicookers', 'Мультиварки и скороварки', 3420, $currentTime, $currentTime],
                [$kitchenAppliancesId, 'Кофеварки и кофемашины', 'coffee-makers', 'Капельные, рожковые, капсульные кофемашины', 3430, $currentTime, $currentTime],
                [$kitchenAppliancesId, 'Встраиваемая техника', 'built-in-appliances', 'Встраиваемая кухонная техника', 3440, $currentTime, $currentTime],
                [$kitchenAppliancesId, 'Электрические плиты', 'electric-stoves', 'Плиты и варочные панели', 3450, $currentTime, $currentTime],
                [$kitchenAppliancesId, 'Хлебопечки', 'bread-makers', 'Автоматические хлебопечи', 3460, $currentTime, $currentTime],
                [$kitchenAppliancesId, 'Кухонные комбайны', 'food-processors', 'Измельчители, блендеры, мясорубки', 3470, $currentTime, $currentTime],
            ]
        );

// Получаем ID категории "Встраиваемая техника"
        $builtInAppliancesId = $this->getDb()->createCommand("SELECT id FROM {{%categories}} WHERE slug = 'built-in-appliances'")->queryScalar();

// Добавляем подкатегории для "Встраиваемая техника"
        $this->batchInsert('{{%categories}}',
            ['parent_id', 'name', 'slug', 'description', 'sort_order', 'created_at', 'updated_at'],
            [
                [$builtInAppliancesId, 'Встраиваемые духовые шкафы', 'built-in-ovens', 'Газовые и электрические духовые шкафы', 3441, $currentTime, $currentTime],
                [$builtInAppliancesId, 'Встраиваемые варочные панели', 'built-in-hobs', 'Индукционные, газовые, комбинированные панели', 3442, $currentTime, $currentTime],
                [$builtInAppliancesId, 'Встраиваемые посудомоечные машины', 'built-in-dishwashers', 'Полноразмерные и компактные модели', 3443, $currentTime, $currentTime],
                [$builtInAppliancesId, 'Встраиваемые холодильники', 'built-in-refrigerators', 'Однокамерные и двухкамерные модели', 3444, $currentTime, $currentTime],
                [$builtInAppliancesId, 'Встраиваемые вытяжки', 'built-in-hoods', 'Купольные, встраиваемые, телескопические вытяжки', 3445, $currentTime, $currentTime],
                [$builtInAppliancesId, 'Встраиваемые микроволновки', 'built-in-microwaves', 'Микроволновые печи для встраивания', 3446, $currentTime, $currentTime],
                [$builtInAppliancesId, 'Встраиваемые винные шкафы', 'built-in-wine-coolers', 'Шкафы для хранения вина', 3447, $currentTime, $currentTime],
                [$builtInAppliancesId, 'Комплекты встраиваемой техники', 'built-in-sets', 'Наборы техники в одном стиле', 3448, $currentTime, $currentTime],
            ]
        );

        // Вставляем подкатегории для Дома и сада
        $this->batchInsert('{{%categories}}',
            ['parent_id', 'name', 'slug', 'description', 'sort_order', 'created_at', 'updated_at'],
            [
                [$homeGardenId, 'Мебель', 'furniture', 'Мебель для дома и офиса', 4100, $currentTime, $currentTime],
                [$homeGardenId, 'Текстиль', 'textiles', 'Постельное белье, шторы, покрывала', 4200, $currentTime, $currentTime],
                [$homeGardenId, 'Посуда', 'tableware', 'Кухонная посуда и столовые приборы', 4300, $currentTime, $currentTime],
                [$homeGardenId, 'Декор', 'home-decor', 'Предметы интерьера и декора', 4400, $currentTime, $currentTime],
                [$homeGardenId, 'Инструменты', 'tools', 'Инструменты для дома и сада',4500, $currentTime, $currentTime],
                [$homeGardenId, 'Садовый инвентарь', 'garden-tools', 'Инвентарь для сада и огорода', 4600, $currentTime, $currentTime],
            ]
        );



        // Вставляем подкатегории для Красоты и здоровья
        $this->batchInsert('{{%categories}}',
            ['parent_id', 'name', 'slug', 'description', 'sort_order', 'created_at', 'updated_at'],
            [
                [$beautyHealthId, 'Косметика', 'cosmetics', 'Декоративная и уходовая косметика', 5100, $currentTime, $currentTime],
                [$beautyHealthId, 'Парфюмерия', 'perfume', 'Духи, туалетная вода', 5200, $currentTime, $currentTime],
                [$beautyHealthId, 'Уход за волосами', 'hair-care', 'Шампуни, бальзамы, средства для укладки', 5300, $currentTime, $currentTime],
                [$beautyHealthId, 'Гигиена', 'hygiene', 'Средства личной гигиены', 5400, $currentTime, $currentTime],
                [$beautyHealthId, 'Витамины', 'vitamins', 'Витамины и БАДы', 5500, $currentTime, $currentTime],
                [$beautyHealthId, 'Медицинские товары', 'medical', 'Аптечки, тонометры и другие медицинские товары', 5600, $currentTime, $currentTime],
            ]
        );

        // Вставляем подкатегории для Спорта и отдыха
        $this->batchInsert('{{%categories}}',
            ['parent_id', 'name', 'slug', 'description', 'sort_order', 'created_at', 'updated_at'],
            [
                [$sportsLeisureId, 'Фитнес', 'fitness', 'Товары для фитнеса', 6100, $currentTime, $currentTime],
                [$sportsLeisureId, 'Велоспорт', 'cycling', 'Велосипеды и аксессуары', 6200, $currentTime, $currentTime],
                [$sportsLeisureId, 'Туризм', 'tourism', 'Туристическое снаряжение', 6300, $currentTime, $currentTime],
                [$sportsLeisureId, 'Рыбалка', 'fishing', 'Товары для рыбалки', 6400, $currentTime, $currentTime],
                [$sportsLeisureId, 'Зимние виды спорта', 'winter-sports', 'Лыжи, сноуборды и аксессуары', 6500, $currentTime, $currentTime],
                [$sportsLeisureId, 'Игры', 'games', 'Настольные игры, мячи и другой инвентарь', 6600, $currentTime, $currentTime],
            ]
        );

        // Вставляем подкатегории для Детских товаров
        $this->batchInsert('{{%categories}}',
            ['parent_id', 'name', 'slug', 'description', 'sort_order', 'created_at', 'updated_at'],
            [
                [$kidsId, 'Игрушки', 'toys', 'Детские игрушки', 7100, $currentTime, $currentTime],
                [$kidsId, 'Коляски', 'strollers', 'Детские коляски и аксессуары', 7200, $currentTime, $currentTime],
                [$kidsId, 'Детская мебель', 'kids-furniture', 'Кроватки, столики и другая мебель', 7300, $currentTime, $currentTime],
                [$kidsId, 'Питание', 'baby-food', 'Детское питание', 7400, $currentTime, $currentTime],
                [$kidsId, 'Гигиена', 'baby-hygiene', 'Подгузники и средства гигиены', 7500, $currentTime, $currentTime],
                [$kidsId, 'Школа', 'school', 'Школьные принадлежности', 7600, $currentTime, $currentTime],
            ]
        );

        // Вставляем подкатегории для Продуктов питания
        $this->batchInsert('{{%categories}}',
            ['parent_id', 'name', 'slug', 'description', 'sort_order', 'created_at', 'updated_at'],
            [
                [$foodId, 'Бакалея', 'groceries', 'Крупы, макароны, специи', 8100, $currentTime, $currentTime],
                [$foodId, 'Молочные продукты', 'dairy', 'Молоко, сыр, йогурты', 8200, $currentTime, $currentTime],
                [$foodId, 'Мясо и птица', 'meat', 'Мясные продукты и птица', 8300, $currentTime, $currentTime],
                [$foodId, 'Овощи и фрукты', 'vegetables-fruits', 'Свежие овощи и фрукты', 8400, $currentTime, $currentTime],
                [$foodId, 'Напитки', 'beverages', 'Соки, вода, чай, кофе', 8500, $currentTime, $currentTime],
                [$foodId, 'Сладости', 'sweets', 'Конфеты, печенье, шоколад', 8600, $currentTime, $currentTime],
            ]
        );

        // Вставляем подкатегории для Бытовая химия
        $this->batchInsert('{{%categories}}',
            ['parent_id', 'name', 'slug', 'description', 'sort_order', 'created_at', 'updated_at'],
            [
                [$chemicalsId, 'Средства для стирки', 'laundry', 'Стиральные порошки, кондиционеры для белья', 9100, $currentTime, $currentTime],
                [$chemicalsId, 'Средства для мытья посуды', 'dishwashing', 'Гели, таблетки для посудомоечных машин', 9200, $currentTime, $currentTime],
                [$chemicalsId, 'Чистящие средства', 'cleaning', 'Для кухни, ванной, стекол, мебели', 9300, $currentTime, $currentTime],
                [$chemicalsId, 'Освежители воздуха', 'air-fresheners', 'Спреи, автоматические освежители', 9400, $currentTime, $currentTime],
                [$chemicalsId, 'Дезинфицирующие средства', 'disinfectants', 'Антисептики, санитайзеры', 9500, $currentTime, $currentTime],
                [$chemicalsId, 'Средства от насекомых', 'pest-control', 'От комаров, муравьев, тараканов', 9600, $currentTime, $currentTime],
            ]
        );


        // Вставляем подкатегории для Автотоваров
        $this->batchInsert('{{%categories}}',
            ['parent_id', 'name', 'slug', 'description', 'sort_order', 'created_at', 'updated_at'],
            [
                [$autoId, 'Автозапчасти', 'auto-parts', 'Запчасти для автомобилей', 10100, $currentTime, $currentTime],
                [$autoId, 'Автохимия', 'auto-chemistry', 'Масла, очистители и другие автохимия', 10200, $currentTime, $currentTime],
                [$autoId, 'Автоаксессуары', 'auto-accessories', 'Чехлы, коврики и другие аксессуары', 10300, $currentTime, $currentTime],
                [$autoId, 'Автоэлектроника', 'auto-electronics', 'Навигаторы, видеорегистраторы', 10400, $currentTime, $currentTime],
                [$autoId, 'Мототехника', 'motorcycle', 'Мотоциклы и аксессуары', 10500, $currentTime, $currentTime],
            ]
        );

        $autoPartsId = $this->getDb()->createCommand("SELECT id FROM {{%categories}} WHERE slug = 'auto-parts'")->queryScalar();

// Добавляем основные подкатегории для автозапчастей
        $this->batchInsert('{{%categories}}',
            ['parent_id', 'name', 'slug', 'description', 'sort_order', 'created_at', 'updated_at'],
            [
                // Группировка по системам автомобиля
                [$autoPartsId, 'Двигатель и компоненты', 'engine-parts', 'Детали двигателя и навесное оборудование', 10110, $currentTime, $currentTime],
                [$autoPartsId, 'Трансмиссия', 'transmission', 'КПП, сцепление, приводные валы', 10120, $currentTime, $currentTime],
                [$autoPartsId, 'Подвеска и рулевое', 'suspension-steering', 'Амортизаторы, рычаги, рулевые рейки', 10130, $currentTime, $currentTime],
                [$autoPartsId, 'Тормозная система', 'brake-system', 'Колодки, диски, суппорты', 10140, $currentTime, $currentTime],
                [$autoPartsId, 'Электрика', 'electrical', 'Аккумуляторы, генераторы, проводка', 10150, $currentTime, $currentTime],
                [$autoPartsId, 'Кузовные детали', 'body-parts', 'Крылья, двери, капоты', 10160, $currentTime, $currentTime],
                [$autoPartsId, 'Салон и комфорт', 'interior', 'Сидения, панели приборов, обивка', 10170, $currentTime, $currentTime],
                [$autoPartsId, 'Система охлаждения', 'cooling-system', 'Радиаторы, помпы, патрубки', 10180, $currentTime, $currentTime],
                [$autoPartsId, 'Фильтры и жидкости', 'filters-fluids', 'Масляные, воздушные фильтры, технические жидкости', 10190, $currentTime, $currentTime],
                [$autoPartsId, 'Для ТО', 'maintenance', 'Расходники для технического обслуживания', 10101, $currentTime, $currentTime]
            ]
        );

// Получаем ID основных подкатегорий
        $enginePartsId = $this->getDb()->createCommand("SELECT id FROM {{%categories}} WHERE slug = 'engine-parts'")->queryScalar();
        $transmissionId = $this->getDb()->createCommand("SELECT id FROM {{%categories}} WHERE slug = 'transmission'")->queryScalar();
        $suspensionId = $this->getDb()->createCommand("SELECT id FROM {{%categories}} WHERE slug = 'suspension-steering'")->queryScalar();
        $brakeSystemId = $this->getDb()->createCommand("SELECT id FROM {{%categories}} WHERE slug = 'brake-system'")->queryScalar();
        $electricalId = $this->getDb()->createCommand("SELECT id FROM {{%categories}} WHERE slug = 'electrical'")->queryScalar();
        $bodyPartsId = $this->getDb()->createCommand("SELECT id FROM {{%categories}} WHERE slug = 'body-parts'")->queryScalar();
        $interiorId = $this->getDb()->createCommand("SELECT id FROM {{%categories}} WHERE slug = 'interior'")->queryScalar();
        $coolingSystemId = $this->getDb()->createCommand("SELECT id FROM {{%categories}} WHERE slug = 'cooling-system'")->queryScalar();
        $filtersId = $this->getDb()->createCommand("SELECT id FROM {{%categories}} WHERE slug = 'filters-fluids'")->queryScalar();
        $maintenanceId = $this->getDb()->createCommand("SELECT id FROM {{%categories}} WHERE slug = 'maintenance'")->queryScalar();

// Детализация категории "Двигатель и компоненты"
        $this->batchInsert('{{%categories}}',
            ['parent_id', 'name', 'slug', 'sort_order', 'created_at', 'updated_at'],
            [
                [$enginePartsId, 'Поршневая группа', 'piston-group', 10111, $currentTime, $currentTime],
                [$enginePartsId, 'ГРМ', 'timing-system', 10112, $currentTime, $currentTime],
                [$enginePartsId, 'Турбины', 'turbos', 10113, $currentTime, $currentTime],
                [$enginePartsId, 'Масляный насос', 'oil-pump', 10114, $currentTime, $currentTime],
                [$enginePartsId, 'Прокладки', 'gaskets', 10115, $currentTime, $currentTime],
                [$enginePartsId, 'Клапаны', 'valves', 10116, $currentTime, $currentTime],
                [$enginePartsId, 'Головка блока цилиндров', 'cylinder-head', 10117, $currentTime, $currentTime],
                [$enginePartsId, 'Система впуска', 'intake-system', 10118, $currentTime, $currentTime],
                [$enginePartsId, 'Система выпуска', 'exhaust-system', 10105, $currentTime, $currentTime]
            ]
        );

// Детализация категории "Трансмиссия"
        $this->batchInsert('{{%categories}}',
            ['parent_id', 'name', 'slug', 'sort_order', 'created_at', 'updated_at'],
            [
                [$transmissionId, 'Сцепление', 'clutch', 10121, $currentTime, $currentTime],
                [$transmissionId, 'Коробка передач', 'gearbox', 10122, $currentTime, $currentTime],
                [$transmissionId, 'Приводные валы', 'drive-shafts', 10123, $currentTime, $currentTime],
                [$transmissionId, 'Раздаточная коробка', 'transfer-case', 10124, $currentTime, $currentTime],
                [$transmissionId, 'Дифференциал', 'differential', 10125, $currentTime, $currentTime],
                [$transmissionId, 'Карданный вал', 'cardan-shaft', 10126, $currentTime, $currentTime],
                [$transmissionId, 'Гидротрансформатор', 'torque-converter', 10127, $currentTime, $currentTime]
            ]
        );

// Детализация категории "Подвеска и рулевое"
        $this->batchInsert('{{%categories}}',
            ['parent_id', 'name', 'slug', 'sort_order', 'created_at', 'updated_at'],
            [
                [$suspensionId, 'Амортизаторы', 'shock-absorbers', 10131, $currentTime, $currentTime],
                [$suspensionId, 'Пружины/рессоры', 'springs', 10132, $currentTime, $currentTime],
                [$suspensionId, 'Рулевые рейки', 'steering-racks', 10133, $currentTime, $currentTime],
                [$suspensionId, 'Шаровые опоры', 'ball-joints', 10134, $currentTime, $currentTime],
                [$suspensionId, 'Сайлентблоки', 'silent-blocks', 10135, $currentTime, $currentTime],
                [$suspensionId, 'Рулевые тяги', 'tie-rods', 10136, $currentTime, $currentTime],
                [$suspensionId, 'Стойки стабилизатора', 'stabilizer-links', 10137, $currentTime, $currentTime],
                [$suspensionId, 'Опорные подшипники', 'strut-bearings', 10138, $currentTime, $currentTime]
            ]
        );

// Детализация категории "Тормозная система"
        $this->batchInsert('{{%categories}}',
            ['parent_id', 'name', 'slug', 'sort_order', 'created_at', 'updated_at'],
            [
                [$brakeSystemId, 'Тормозные колодки', 'brake-pads', 10141, $currentTime, $currentTime],
                [$brakeSystemId, 'Тормозные диски', 'brake-discs', 10142, $currentTime, $currentTime],
                [$brakeSystemId, 'Тормозные суппорты', 'brake-calipers', 10143, $currentTime, $currentTime],
                [$brakeSystemId, 'Тормозные шланги', 'brake-hoses', 10144, $currentTime, $currentTime],
                [$brakeSystemId, 'Главный тормозной цилиндр', 'master-cylinder', 10145, $currentTime, $currentTime],
                [$brakeSystemId, 'Тормозная жидкость', 'brake-fluid', 10146, $currentTime, $currentTime],
                [$brakeSystemId, 'Вакуумный усилитель', 'brake-booster', 10147, $currentTime, $currentTime]
            ]
        );

// Детализация категории "Электрика"
        $this->batchInsert('{{%categories}}',
            ['parent_id', 'name', 'slug', 'sort_order', 'created_at', 'updated_at'],
            [
                [$electricalId, 'Аккумуляторы', 'batteries', 10151, $currentTime, $currentTime],
                [$electricalId, 'Генераторы', 'alternators', 10152, $currentTime, $currentTime],
                [$electricalId, 'Стартеры', 'starters', 10153, $currentTime, $currentTime],
                [$electricalId, 'Блоки управления', 'ecus', 10154, $currentTime, $currentTime],
                [$electricalId, 'Датчики', 'sensors', 10155, $currentTime, $currentTime],
                [$electricalId, 'Проводка', 'wiring', 10156, $currentTime, $currentTime],
                [$electricalId, 'Предохранители', 'fuses', 10157, $currentTime, $currentTime],
                [$electricalId, 'Лампы', 'bulbs', 10158, $currentTime, $currentTime]
            ]
        );

// Детализация категории "Кузовные детали"
        $this->batchInsert('{{%categories}}',
            ['parent_id', 'name', 'slug', 'sort_order', 'created_at', 'updated_at'],
            [
                [$bodyPartsId, 'Крылья', 'fenders', 10161, $currentTime, $currentTime],
                [$bodyPartsId, 'Двери', 'doors', 10162, $currentTime, $currentTime],
                [$bodyPartsId, 'Капоты', 'hoods', 10163, $currentTime, $currentTime],
                [$bodyPartsId, 'Бамперы', 'bumpers', 10164, $currentTime, $currentTime],
                [$bodyPartsId, 'Лобовые стекла', 'windshields', 10165, $currentTime, $currentTime],
                [$bodyPartsId, 'Зеркала', 'mirrors', 10166, $currentTime, $currentTime],
                [$bodyPartsId, 'Пороги', 'sills', 10167, $currentTime, $currentTime],
                [$bodyPartsId, 'Крышка багажника', 'trunk-lids', 10168, $currentTime, $currentTime]
            ]
        );

// Детализация категории "Салон и комфорт"
        $this->batchInsert('{{%categories}}',
            ['parent_id', 'name', 'slug', 'sort_order', 'created_at', 'updated_at'],
            [
                [$interiorId, 'Сиденья', 'seats', 10171, $currentTime, $currentTime],
                [$interiorId, 'Приборные панели', 'dashboards', 10172, $currentTime, $currentTime],
                [$interiorId, 'Обогревы', 'heaters', 10173, $currentTime, $currentTime],
                [$interiorId, 'Кондиционеры', 'ac-systems', 10174, $currentTime, $currentTime],
                [$interiorId, 'Аудиосистемы', 'audio-systems', 10175, $currentTime, $currentTime],
                [$interiorId, 'Коврики', 'mats', 10176, $currentTime, $currentTime],
                [$interiorId, 'Рулевые колеса', 'steering-wheels', 10177, $currentTime, $currentTime],
                [$interiorId, 'Дефлекторы', 'vents', 10178, $currentTime, $currentTime]
            ]
        );

// Детализация категории "Система охлаждения"
        $this->batchInsert('{{%categories}}',
            ['parent_id', 'name', 'slug', 'sort_order', 'created_at', 'updated_at'],
            [
                [$coolingSystemId, 'Радиаторы', 'radiators', 10181, $currentTime, $currentTime],
                [$coolingSystemId, 'Водяные помпы', 'water-pumps', 10182, $currentTime, $currentTime],
                [$coolingSystemId, 'Термостаты', 'thermostats', 10183, $currentTime, $currentTime],
                [$coolingSystemId, 'Вентиляторы', 'cooling-fans', 10184, $currentTime, $currentTime],
                [$coolingSystemId, 'Патрубки', 'hoses', 10185, $currentTime, $currentTime],
                [$coolingSystemId, 'Расширительные бачки', 'expansion-tanks', 10186, $currentTime, $currentTime],
                [$coolingSystemId, 'Радиаторы печки', 'heater-cores', 10187, $currentTime, $currentTime]
            ]
        );

// Детализация категории "Фильтры и жидкости"
        $this->batchInsert('{{%categories}}',
            ['parent_id', 'name', 'slug', 'sort_order', 'created_at', 'updated_at'],
            [
                [$filtersId, 'Масляные фильтры', 'oil-filters', 10191, $currentTime, $currentTime],
                [$filtersId, 'Воздушные фильтры', 'air-filters', 10192, $currentTime, $currentTime],
                [$filtersId, 'Топливные фильтры', 'fuel-filters', 10193, $currentTime, $currentTime],
                [$filtersId, 'Салонные фильтры', 'cabin-filters', 10194, $currentTime, $currentTime],
                [$filtersId, 'Моторные масла', 'engine-oils', 10195, $currentTime, $currentTime],
                [$filtersId, 'Трансмиссионные масла', 'transmission-oils', 10196, $currentTime, $currentTime],
                [$filtersId, 'Антифриз', 'coolant', 10197, $currentTime, $currentTime],
                [$filtersId, 'Жидкость ГУР', 'power-steering-fluid', 10198, $currentTime, $currentTime]
            ]
        );

// Детализация категории "Для ТО"
        $this->batchInsert('{{%categories}}',
            ['parent_id', 'name', 'slug', 'sort_order', 'created_at', 'updated_at'],
            [
                [$maintenanceId, 'Моторные масла', 'maintenance-oils', 10102, $currentTime, $currentTime],
                [$maintenanceId, 'Свечи зажигания', 'spark-plugs', 10103, $currentTime, $currentTime],
                [$maintenanceId, 'Ремни ГРМ', 'timing-belts', 10104, $currentTime, $currentTime],
                [$maintenanceId, 'Тормозные колодки', 'maintenance-pads', 10105, $currentTime, $currentTime],
                [$maintenanceId, 'Щетки стеклоочистителей', 'wiper-blades', 10106, $currentTime, $currentTime],
                [$maintenanceId, 'Комплекты ТО', 'maintenance-kits', 10107, $currentTime, $currentTime],
                [$maintenanceId, 'Жидкости для ТО', 'maintenance-fluids', 10108, $currentTime, $currentTime],
                [$maintenanceId, 'Фильтры для ТО', 'maintenance-filters', 10109, $currentTime, $currentTime]
            ]
        );





         //2. Добавляем триггер для обновления  updated_at
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
        CREATE TRIGGER categories_updated_at_trigger
        BEFORE UPDATE ON "categories"
        FOR EACH ROW
        EXECUTE FUNCTION update_updated_at();
    ');

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {

        $this->execute('DROP TRIGGER IF EXISTS categories_updated_at_trigger ON "categories";');
        $this->execute('DROP FUNCTION IF EXISTS update_updated_at();');
        $this->dropTable('{{%categories}}');
    }
}
