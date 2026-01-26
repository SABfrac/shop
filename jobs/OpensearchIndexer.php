<?php


namespace app\jobs;


use app\models\Offers;
use app\helper\DateTimeHelper;
use app\services\catalog\DataNormalizerService;
use app\models\ProductImage;


use Yii;




/**
 * Асинхронная задача для массовой индексации офферов в OpenSearch.
 */
class OpensearchIndexer
{

    private array $runtimeCategoryCache = [];



    public function __construct(
        private DataNormalizerService $normalizer
    ) {}


    public function bulkIndexOffers(array $offerIds, ?int $reportId = null):bool
    {

        $offerIds = array_map('intval', array_unique(array_slice($offerIds, 0, 5000)));

        if (empty($offerIds)) {
            return false;
        }

        $offers = Offers::find()
            ->with(['sku.globalProduct', 'sku.globalProduct.brand'])
            ->where(['id' => $offerIds])
            ->asArray()
            ->all();

        $index = Yii::$app->opensearch->index;
        $bulkBody = [];
        $foundIds = []; // ID, которые мы реально нашли в базе

        // 1. Формируем операции индексации (UPSERT)
        foreach ($offers as $offer) {
            $foundIds[] = $offer['id']; // Запоминаем ID
            $isActive = ($offer['status'] === Offers::STATUS_ACTIVE);

            $version = $offer['updated_at'] ? strtotime($offer['updated_at']) : time();
            if ($version <= 0) $version = time();

            $bulkBody[] = [
                'index' => [
                    '_index' => $index,
                    '_id' => (string)$offer['id'],
                    'version' => $version,
                    'version_type' => 'external_gte',
                ],
            ];
            $bulkBody[] = $this->prepareDocument($offer, $isActive);
        }

        // 2. Вычисляем ID для удаления ПОСЛЕ цикла
        // Те ID, которые были запрошены ($offerIds), но не нашлись в базе ($foundIds)
        $idsToDelete = array_diff($offerIds, $foundIds);

        foreach ($idsToDelete as $id) {
            $bulkBody[] = [
                'delete' => [
                    '_index' => $index,
                    '_id' => (string)$id,
                ],
            ];
        }

        // 3. Отправка
        if (!empty($bulkBody)) {
            // Массив теперь будет содержать максимум 2000 элементов (1000 заголовков + 1000 тел),
            // а не 500 000, как раньше. Память не переполнится.
            Yii::$app->opensearch->bulk($bulkBody);

            // Очищаем память
            unset($bulkBody);
            unset($offers);
        }


        return true;
    }


    /**
     * Подготавливает документ оффера для OpenSearch.
     */
    protected function prepareDocument(array $offer,bool $isActive = true): array
    {
        if (
            empty($offer['sku']) ||
            empty($offer['sku']['globalProduct'])
        ) {
            Yii::warning("Offer {$offer['id']} has no valid product structure. Skipped.", 'opensearch');
            return []; // будет отфильтрован в bulkIndexOffers
        }

        $product = $offer['sku']['globalProduct'];
        $brand = $product['brand'] ?? null;

        // === Атрибуты из variant_values ===
        $attributes = [];
        $flatAttributes = [];

        $rawVariantValues = $offer['sku']['variant_values'] ?? '';

        if (is_string($rawVariantValues)) {
            $variantValues = json_decode($rawVariantValues, true) ?: [];
        } elseif (is_array($rawVariantValues)) {
            $variantValues = $rawVariantValues;
        } else {
            $variantValues = [];
        }

        foreach ($variantValues as $item) {
            if (!isset($item['name'], $item['value'])) {
                continue;
            }

            $attrName = (string)$item['name'];
            $attrValue = (string)$item['value'];

            $attributes[] = [
                'name' => $attrName,
                'value' => $attrValue,
            ];

            if ($attrName === 'Цвет') {
                $flatAttributes['Цвет'] = $attrValue;
            } elseif ($attrName === 'Размер') {
                $flatAttributes['Размер'] = $attrValue;
            } elseif ($attrName === 'weight') {
                $flatAttributes['weight'] = is_numeric($attrValue) ? (float)$attrValue : null;
            }
            // Добавьте другие flat-поля по мере необходимости
        }

        // === Поисковые термины ===
        $searchTerms = [trim($product['canonical_name'] ?? '')];

        if (!empty($product['category_id'])) {
            $searchTerms = array_merge(
                $searchTerms,
                $this->getCategorySearchTerms((int)$product['category_id'])
            );
        }

        if ($brand) {
            $searchTerms[] = trim($brand['name'] ?? '');
        }

        $imageThumbKey = null;
        if (!empty($offer['sku_id'])) {
            // Ищем главное изображение для SKU (или global_product, если offer без SKU)
            $mainImage = ProductImage::find()
                ->where([
                    'entity_type' => 'offer',
                    'entity_id' => $offer['id'],
                    'is_main' => true,
                ])
                ->orWhere([
                    'entity_type' => 'global_product',
                    'entity_id' => $product['id'],
                    'is_main' => true,
                ])
                ->orderBy(['is_main' => SORT_DESC]) // offer > global_product
                ->asArray()
                ->one();

            if ($mainImage) {
                $imageThumbKey =  $mainImage ? $mainImage['storage_path']:null; // например: "vendors/1/offers/123/img_xxx.jpg"
            }
        }



        // === Формируем документ ===
        $doc = [
            // === Основные ID ===
            'product_id' => (int)($product['id'] ?? 0),
            'sku_id' => (string)($offer['sku_id'] ?? ''),
            'category_id' => (int)($product['category_id'] ?? 0),

            // === Продукт ===
            'product_name' => trim($product['canonical_name'] ?? ''),
            'brand_id' => $brand ? (int)($brand['id'] ?? 0) : null,
            'brand_name' => $brand ? trim($brand['name'] ?? '') : null,

            // === Для автодополнения ===
            'suggest' => [
                'input' => $this->generateSuggestInputs(
                    $product['canonical_name'] ?? '',
                    $brand ? ($brand['name'] ?? '') : null
                ),
                'weight' => 10
            ],

            // === Оффер ===
            'vendor_id' => (int)($offer['vendor_id'] ?? 0),
            'vendor_sku' => $offer['vendor_sku'] ?? null,
            'price' => (float)($offer['price'] ?? 0.0),
            'stock' => (int)($offer['stock'] ?? 0),
            'is_active' => $isActive,
            'condition' => $offer['condition'] ?? null,
            'warranty' => isset($offer['warranty']) ? (int)$offer['warranty'] : null,
            'status' => $offer['status'] ?? null,
            'sort_order' => (int)($offer['sort_order'] ?? 0),

            // === Атрибуты ===
            'attributes' => $attributes,
            'flat_attributes' => $flatAttributes,
            // ключ к оригиналу в MinIO
            'image_thumb_key'=>$imageThumbKey,

            // === Поле для поиска ===
            'full_search' => implode(' ', array_filter($searchTerms)),



            // === Временные метки ===
            'created_at' => !empty($offer['created_at'])
                ? DateTimeHelper::toUtc($offer['created_at'])
                : null,
            'updated_at' => !empty($offer['updated_at'])
                ? DateTimeHelper::toUtc($offer['updated_at'])
                : null,
        ];



        // Пример (если у вас есть логика формирования пути):
        // $doc['image_thumb_key'] = "vendors/{$offer['vendor_id']}/thumbs/{$offer['sku_id']}.jpg";
//       логику создания (генерации пути через Yii::$app->s3->getPresignedUrl) смотрим пример в actionReportStatus
//        Чек лист
//    1)добавляем поле image_thumb_key в индексаци
//    3)пишем загружалку картинок для одиночного оффера бэкенд находиться VendorProductManagementService
//    3)с бека картинка должна попадать на индексацию (путь сейвим в image_thumb_key) и в Minlo на хранение (логика добавления на хранения пример в
//        FinalizeFeedReportJob вызов  Yii::$app->s3->upload)
//        4)для карточек которые появляются в поиске делаем динамический ресайз
//Использовать MinIO + Lambda-подобный триггер или отдельный сервис (например, imaginary, thumbor, imgproxy)
//        для генерации превью «на лету» по запросу
//    Тогда в OpenSearch хранится только image_key_original, а URL для превью генерируется по шаблону без подписи
//    (если imgproxy авторизует запросы от бэкенда).Это быстрее, потому что не нужно генерировать  подписи — только подставить ключ в шаблон

        return $doc;
    }


    private function getCategorySearchTerms(int $categoryId): array
    {

        if (isset($this->runtimeCategoryCache[$categoryId])) {
            return $this->runtimeCategoryCache[$categoryId];
        }


        // Ключ кэша: уникален для каждой категории
        $cacheKey = "category_search_terms_{$categoryId}";

        // Пытаемся получить из кэша
        $terms = Yii::$app->cache->get($cacheKey);

        if ($terms === false) {
            // Не найдено в кэше — читаем из БД
            $terms = (new \yii\db\Query())
                ->select('term')
                ->from('{{%category_search_terms}}')
                ->where(['category_id' => $categoryId])
                ->column();




            Yii::$app->cache->set($cacheKey, $terms, 7200);
        }
        $result = $terms ?: [];
        $this->runtimeCategoryCache[$categoryId] = $result;
        return $result;
    }

    /**
     * Генерирует варианты ввода для completion suggester.
     */
    private function generateSuggestInputs(string $productName, ?string $brandName): array
    {
        $cleanProductName = trim($productName);
        if ($cleanProductName === '') {
            return [];
        }

        $inputs = [
            mb_strtolower($cleanProductName, 'UTF-8')
        ];

        // Если бренд задан — пытаемся сгенерировать вариант без него
        if ($brandName !== null && ($trimmedBrand = trim($brandName)) !== '') {
            // Используем уже отлаженную логику удаления бренда по границам слов
            $withoutBrand = $this->normalizer->mathKeyNormalizer($cleanProductName, $trimmedBrand);

            // mathKeyNormalizer возвращает null только если вход пуст — здесь не наш случай
            if ($withoutBrand !== null && $withoutBrand !== '') {
                // Убираем возможные двойные пробелы и нормализуем
                $withoutBrand = preg_replace('/\s+/', ' ', trim($withoutBrand));
                if ($withoutBrand !== '') {
                    $inputs[] = $withoutBrand;
                }
            }
            $inputs[] = mb_strtolower($brandName, 'UTF-8');
        }

        // Убираем дубли, сохраняя порядок
        return array_values(array_unique($inputs));
    }


}