<?php

namespace app\commands;
use Yii;
use yii\console\Controller;
use yii\helpers\Console;
use yii\console\ExitCode;



class FeedController extends Controller
{
    private $colors = [
        'Белый', 'Зеленый', 'Синий', 'Титан', 'Черный', 'Красный',
        'Серый', 'Розовый', 'Желтый', 'Фиолетовый', 'Оранжевый',
        'Бежевый', 'Коричневый', 'Бордовый', 'Мятный'
    ];

    private $ramOptions = [4, 6, 8, 12];
    private $storageOptions = [64, 128, 256, 512, 1024, 2048]; // в ГБ
    private $warrantyOptions = [12, 18, 24, 36];
    private $screenSizes = [5.5, 5.8, 6.1, 6.4, 6.5, 6.7, 6.8, 6.9, 7.0, 7.6];

    // Бренды с уникальными префиксами имён
    private $brands = [
        'Apple' => ['iPhone', 'iPad Mini', 'iPad Pro', 'iPad Air'],
        'Samsung' => ['Galaxy S', 'Galaxy A', 'Galaxy Z Fold', 'Galaxy Z Flip', 'Galaxy Note'],
        'Xiaomi' => ['Redmi', 'Redmi Note', 'Mi', 'Poco', 'Black Shark'],
        'Huawei' => ['P', 'Mate', 'Nova', 'Y', 'Honor'],
        'OnePlus' => ['Nord', 'Open', 'Ace', 'Pro', 'Ultra'],
        'Google' => ['Pixel', 'Pixel Pro', 'Pixel Fold', 'Pixel A'],
        'Sony' => ['Xperia', 'Xperia Pro', 'Xperia Compact', 'Xperia XZ'],
        'Oppo' => ['Find X', 'Reno', 'A', 'F', 'K'],
        'Vivo' => ['X', 'V', 'Y', 'S', 'iQOO'],
        'Realme' => ['GT', 'C', 'Narzo', 'Q', 'X'],
        'Motorola' => ['Edge', 'Moto G', 'Moto E', 'Razr', 'ThinkPhone'],
        'Nokia' => ['XR', 'G', 'C', 'X', 'PureView'],
        'Asus' => ['ROG Phone', 'Zenfone', 'ROG', 'Padfone', 'Zenbook'],
        'LG' => ['Velvet', 'Wing', 'V', 'K', 'Q'],
        'ZTE' => ['Axon', 'Blade', 'Nubia', 'RedMagic', 'Libero'],
        'Tecno' => ['Camon', 'Spark', 'Pova', 'Phantom', 'Pop'],
        'Infinix' => ['Note', 'Hot', 'Zero', 'Smart', 'S'],
        'Honor' => ['Magic', 'X', 'Play', 'View', 'Choice'],
        'Meizu' => ['Pro', 'Note', 'M', 'X', 'V'],
        'Lenovo' => ['Legion', 'Tab', 'K', 'Z', 'A'],
    ];

    // Модификаторы для имён (уникальные суффиксы)
    private $modelNumbers = [];
    private $modelSuffixes = ['', ' Plus', ' Pro', ' Max', ' Ultra', ' Lite', ' SE', ' FE', ' Mini', ' Edge'];

    // Счётчик для уникальности
    private $generatedCount = 0;
    private $usedProductNames = [];
    private $productNameIndex = [];

    /**
     * Генерация фида
     * @param int $rows Количество строк
     * @param string $output Путь к файлу
     */
    public function actionGenerate($rows = 1000000, $output = '@runtime/generated_feed.csv')
    {
        $outputPath = \Yii::getAlias($output);
        $startTime = microtime(true);

        $this->stdout("Генерация фида на {$rows} строк...\n", Console::FG_GREEN);
        $this->stdout("Файл: {$outputPath}\n\n");

        // Подготовка уникальных имён
        $this->prepareProductNames($rows);

        // Диагностика: сколько товаров и сколько максимум строк можем сгенерировать
        $totalProducts = array_sum(array_map('count', $this->productNameIndex));
        $maxCombinations = $totalProducts
            * count($this->colors)
            * count($this->ramOptions)
            * count($this->storageOptions);

        $this->stdout("Уникальных товаров: {$totalProducts}\n");
        $this->stdout("Максимум строк без ограничений: " . number_format($maxCombinations, 0, '', ' ') . "\n\n");

        $file = fopen($outputPath, 'w');
        if (!$file) {
            $this->stderr("Не удалось открыть файл для записи\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        // Заголовки (порядок строго соблюдён)
        $headers = [
            'sku_code',
            'product_name',
            'brand',
            'price',
            'stock',
            'warranty',
            'Цвет',
            'Оперативная память (ГБ)',
            'Встроенная память (ГБ)',
            'Диагональ экрана (дюйм)'
        ];
        fwrite($file, "\xEF\xBB\xBF"); // UTF-8 BOM
        fputcsv($file, $headers, ';');

        // Генерация
        $batchSize = 10000;
        $generated = 0;

        foreach ($this->generateCombinations() as $combo) {
            if ($generated >= $rows) {
                break;
            }

            // Выбираем ПО ОДНОМУ значению гарантии и экрана
            $warranty = $this->warrantyOptions[array_rand($this->warrantyOptions)];
            $screen = $this->screenSizes[array_rand($this->screenSizes)];

            $row = [
                'sku_code' => $this->generateSku(),
                'product_name' => $combo['product_name'],
                'brand' => $combo['brand'],
                'price' => $this->generatePrice($combo['ram'], $combo['storage']),
                'stock' => mt_rand(0, 500),
                'warranty' => $warranty,
                'Цвет' => $combo['color'],
                'Оперативная память (ГБ)' => $combo['ram'],
                'Встроенная память (ГБ)' => $combo['storage'] >= 1024 ? ($combo['storage'] / 1024) : $combo['storage'],
                'Диагональ экрана (дюйм)' => str_replace('.', ',', (string)$screen),
            ];

            fputcsv($file, array_values($row), ';');
            $generated++;

            if ($generated % $batchSize === 0) {
                $progress = round(($generated / $rows) * 100, 1);
                $memory = round(memory_get_usage(true) / 1024 / 1024, 2);
                $this->stdout("\rПрогресс: {$progress}% ({$generated}/{$rows}) | Память: {$memory} MB");
            }
        }

        fclose($file);

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        $fileSize = round(filesize($outputPath) / 1024 / 1024, 2);

        $this->stdout("\n\n", Console::FG_GREEN);
        $this->stdout("✓ Генерация завершена!\n", Console::FG_GREEN);
        $this->stdout("  Строк: {$generated}\n");
        $this->stdout("  Время: {$duration} сек\n");
        $this->stdout("  Размер файла: {$fileSize} MB\n");

        return ExitCode::OK;
    }

    /**
     * Генератор всех комбинаций (использует yield для экономии памяти)
     */
    private function generateCombinations()
    {
        foreach ($this->productNameIndex as $brand => $productNames) {
            foreach ($productNames as $productName) {
                foreach ($this->colors as $color) {
                    foreach ($this->ramOptions as $ram) {
                        foreach ($this->storageOptions as $storage) {
                            yield [
                                'brand' => $brand,
                                'product_name' => $productName,
                                'color' => $color,
                                'ram' => $ram,
                                'storage' => $storage,
                            ];
                        }
                    }
                }
            }
        }
    }

    /**
     * Подготовка уникальных имён товаров для каждого бренда
     */
    private function prepareProductNames($totalRows)
    {
        $combinationsPerProduct = count($this->colors) * count($this->ramOptions) * count($this->storageOptions);
        $requiredProducts = (int)ceil($totalRows / $combinationsPerProduct) + 100;

        $this->stdout("Требуется уникальных товаров: {$requiredProducts}\n");

        // Генерация большого пула номеров
        $modelNumbers = [];
        for ($i = 1; $i <= 500; $i++) {
            $modelNumbers[] = (string)$i;
            $modelNumbers[] = $i . 's';
            $modelNumbers[] = $i . 'i';
            if ($i <= 300) {
                $modelNumbers[] = $i . ' Pro';
                $modelNumbers[] = $i . ' Ultra';
            }
        }
        for ($i = 1; $i <= 200; $i++) {
            $modelNumbers[] = '202' . (3 + ($i % 3)) . '-' . $i;
        }
        $letters = range('A', 'Z');
        foreach ($letters as $letter) {
            for ($i = 1; $i <= 20; $i++) {
                $modelNumbers[] = $letter . $i;
            }
        }
        shuffle($modelNumbers);

        // Инициализация
        $this->usedProductNames = [];
        $this->productNameIndex = [];
        foreach (array_keys($this->brands) as $brand) {
            $this->productNameIndex[$brand] = [];
        }

        $generated = 0;
        $brandKeys = array_keys($this->brands);
        $totalBrands = count($brandKeys);
        $attempts = 0;
        $maxAttempts = $requiredProducts * 10; // защита от зацикливания

        while ($generated < $requiredProducts && $attempts < $maxAttempts) {
            // Циклический выбор бренда: 0,1,2,...,19,0,1,2...
            $brand = $brandKeys[$generated % $totalBrands];
            $prefixes = $this->brands[$brand];

            $prefix = $prefixes[array_rand($prefixes)];
            $number = $modelNumbers[array_rand($modelNumbers)];
            $suffix = $this->modelSuffixes[array_rand($this->modelSuffixes)];

            $name = trim("{$prefix} {$number}{$suffix}");

            if (!isset($this->usedProductNames[$name])) {
                $this->usedProductNames[$name] = $brand;
                $this->productNameIndex[$brand][] = $name;
                $generated++;
            }
            $attempts++;
        }

        $actualTotal = array_sum(array_map('count', $this->productNameIndex));
        $this->stdout("Создано уникальных товаров: {$actualTotal}\n");

        // Вывод статистики по брендам
        foreach ($brandKeys as $brand) {
            $count = count($this->productNameIndex[$brand]);
            if ($count > 0) {
                $this->stdout("  {$brand}: {$count}\n");
            }
        }
        $this->stdout("\n");
    }

    /**
     * Генерация уникального SKU
     */
    private function generateSku()
    {
        $this->generatedCount++;
        return 'SKU-' . str_pad($this->generatedCount, 8, '0', STR_PAD_LEFT)
            . '-' . strtoupper(substr(md5(mt_rand()), 0, 4));
    }

    /**
     * Генерация цены на основе характеристик
     */
    private function generatePrice($ram, $storage)
    {
        $basePrice = mt_rand(15000, 50000);

        // Наценка за RAM
        $ramMultiplier = [4 => 1, 6 => 1.15, 8 => 1.3, 12 => 1.5];
        $basePrice *= $ramMultiplier[$ram] ?? 1;

        // Наценка за память
        $storageMultiplier = [
            64 => 1,
            128 => 1.1,
            256 => 1.25,
            512 => 1.45,
            1024 => 1.7,
            2048 => 2.0
        ];
        $basePrice *= $storageMultiplier[$storage] ?? 1;

        // Округление до 990
        return (int)(round($basePrice / 1000) * 1000) - 10;
    }

}

