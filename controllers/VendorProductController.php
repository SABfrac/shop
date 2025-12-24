<?php

namespace app\controllers;
use app\services\VendorProduct\VendorProductManagementService;
use app\models\ProductForm;
use app\models\ProductSkus;
use yii\web\Controller;
use yii\web\{ForbiddenHttpException, NotFoundHttpException, ServerErrorHttpException};
use app\traits\VendorAuthTrait;
use Yii;
use app\models\Offers;
use app\models\CategoryAttributeOption;


class VendorProductController extends Controller
{
    use VendorAuthTrait;

    /**
     * Создаёт или обновляет GlobalProduct, SKU и Offer.
     */
    public function actionCreateOrUpdate()
    {
        $vendorId = $this->getAuthorizedVendorId();
        $input = Yii::$app->request->getBodyParams();

        // Валидация минимально необходимых полей
        if (!isset($input['category_id']) || !isset($input['product_name'])) {
            return $this->asJson(['success' => false, 'error' => 'category_id и product_name обязательны']);
        }

        try {
            return $this->asJson([
                'success' => true,
                'data' => (new VendorProductManagementService())->createOrUpdateGlobalProductAndSku($input, $vendorId),
            ]);
        } catch (\Throwable $e) {
            Yii::error([
                'message' => 'API actionCreateOrUpdate failed',
                'error' => $e->getMessage(),
                'vendor_id' => $vendorId,
                'input' => $input,
            ], 'api-error');
            return $this->asJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Получает SKU и связанные Offer для GlobalProduct и Vendor.
     * Используется для загрузки данных в форму после создания SKU/Offer.
     */
    public function actionGetSkusAndOffers()
    {
        $vendorId = $this->getAuthorizedVendorId();
        $request = Yii::$app->request;
        $globalProductId = $request->get('global_product_id'); // Используем GET параметр

        if (!$globalProductId) {
            return $this->asJson(['success' => false, 'error' => 'global_product_id обязателен']);
        }

        try {
            $result = $this->getSkusAndOffersForVendor($globalProductId, $vendorId);
            return $this->asJson([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            Yii::error([
                'message' => 'API actionGetSkusAndOffers failed',
                'error' => $e->getMessage(),
                'vendor_id' => $vendorId,
                'global_product_id' => $globalProductId,
            ], 'api-error');
            return  $this->asJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }


    /**
     * Получает опции для атрибутов (по ID атрибутов) в рамках категории.
     * Используется для заполнения select-ов в форме.
     */
    public function actionGetCategoryAttributeOptions()
    {

        $categoryId = Yii::$app->request->get('category_id');
        $attributeIdsParam = Yii::$app->request->get('attribute_ids'); // Ожидаем строку вида "1,2,3"

        if (!$categoryId) {
            return $this->asJson(['success' => false, 'error' => 'category_id обязателен']);
        }

        $attributeIds = [];
        if ($attributeIdsParam) {
            $attributeIds = array_map('intval', explode(',', $attributeIdsParam));
            $attributeIds = array_filter($attributeIds); // Убираем нули, если были
        }

        try {
            $options = $this->getCategoryAttributeOptions($categoryId, $attributeIds);
            return $this->asJson([
                'success' => true,
                'items' => $options, // Массив объектов опций
            ]);
        } catch (\Throwable $e) {
            Yii::error([
                'message' => 'API actionGetCategoryAttributeOptions failed',
                'error' => $e->getMessage(),
                'category_id' => $categoryId,
                'attribute_ids' => $attributeIds,
            ], 'api-error');
            return $this->asJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Внутренний метод для получения опций атрибутов.
     */
    private function getCategoryAttributeOptions(int $categoryId, array $attributeIds): array
    {
        $query = CategoryAttributeOption::find()
            ->select(['id', 'attribute_id', 'value', 'slug', 'sort_order'])
            ->where(['category_id' => $categoryId]);

        if (!empty($attributeIds)) {
            $query->andWhere(['attribute_id' => $attributeIds]);
        }

        return $query->asArray()->all();
    }

    /**
     * Внутренний метод для получения SKU и Offers.
     */
    private function getSkusAndOffersForVendor(int $globalProductId, int $vendorId): array
    {
        // Запрос для получения SKU, связанных с GlobalProduct
        $skus = ProductSkus::find()
            ->where(['global_product_id' => $globalProductId])
            ->asArray()
            ->all();

        $skuIds = array_column($skus, 'id');
        $skuMap = []; // Карта id => sku_data для быстрого доступа
        foreach ($skus as $sku) {
            $skuMap[$sku['id']] = $sku;
        }

        $offers = [];
        $selectedSkuIds = [];

        if (!empty($skuIds)) {
            // Запрос для получения Offers, связанных с SKU и Vendor
            $offersQuery = Offers::find()
                ->where([
                    'vendor_id' => $vendorId,
                    'sku_id' => $skuIds, // Фильтр по SKU, связанным с GP
                ])
                ->asArray()
                ->all();

            foreach ($offersQuery as $offer) {
                $skuId = $offer['sku_id'];
                $offers[$skuId] = $offer;
                $selectedSkuIds[] = $skuId; // Выбираем все SKU, для которых есть Offer
            }
        }

        return [
            'skus' => array_values($skuMap), // Возвращаем массив SKU
            'offers' => $offers, // Возвращаем ассоциативный массив offer_data по sku_id
            'selected_sku_ids' => $selectedSkuIds, // Возвращаем список ID SKU для выделения
        ];
    }

}