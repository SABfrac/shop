<?php

namespace app\controllers;
use app\models\ProductImage;
use app\services\VendorProduct\VendorProductManagementService;
use app\models\ProductForm;
use app\models\ProductSkus;
use yii\web\Controller;
use yii\web\{ForbiddenHttpException, NotFoundHttpException, ServerErrorHttpException};
use app\traits\VendorAuthTrait;
use Yii;
use app\models\Offers;
use app\models\CategoryAttributeOption;
use yii\web\BadRequestHttpException;
use app\commands\RabbitMqController;



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
            return $this->asJson(['success' => false, 'error' => $e->getMessage()]);
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


    public function actionRequestImageUpload()
    {
        $this->requirePostRequest();
        $vendorId = $this->getAuthorizedVendorId();

        $entityType = Yii::$app->request->post('entity_type');
        $entityId = (int)Yii::$app->request->post('entity_id');
        $filenames = (array)Yii::$app->request->post('filenames');

        if (!in_array($entityType, ['global_product', 'offer'])) {
            throw new BadRequestHttpException('Неверный entity_type');
        }
        if (count($filenames) > 5) {
            throw new BadRequestHttpException('Максимум 5 изображений за раз');
        }

        // 🔒 Проверка прав
        if ($entityType === 'offer') {
            $exists = Offers::find()->where(['id' => $entityId, 'vendor_id' => $vendorId])->exists();
        } else {
            $exists = Offers::find()
                ->alias('o')
                ->innerJoin(['s' => 'product_skus'], 's.id = o.sku_id')
                ->andWhere(['o.vendor_id' => $vendorId, 's.global_product_id' => $entityId])
                ->exists();
        }
        if (!$exists) {
            throw new ForbiddenHttpException('Нет доступа к этой сущности');
        }

        // Проверка общего лимита
        $existingCount = ProductImage::find()
            ->where(['entity_type' => $entityType, 'entity_id' => $entityId])
            ->count();
        if ($existingCount + count($filenames) > 5) {
            throw new BadRequestHttpException('Общее количество изображений не может превышать 5');
        }

        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
        $urls = [];

        foreach ($filenames as $name) {
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExts)) {
                throw new BadRequestHttpException("Недопустимый формат: $name");
            }

            $safeName = preg_replace('/[^a-z0-9._-]/i', '_', basename($name));
            $path = "vendors/{$vendorId}/{$entityType}s/{$entityId}/" . uniqid('img_', true) . '_' . $safeName;
            $uploadUrl = Yii::$app->s3Images->getPresignedUrl($path, '+1 hour', null, 'PUT','http://127.0.0.1:9000'); // важно: PUT

            $urls[$name] = [
                'upload_url' => $uploadUrl,
                'storage_path' => $path,
            ];
        }

        return $this->asJson(['urls' => $urls]);
    }


    public function actionConfirmImages()
    {
        $this->requirePostRequest();
        $vendorId = $this->getAuthorizedVendorId();

        $entityType = Yii::$app->request->post('entity_type');
        $entityId = (int)Yii::$app->request->post('entity_id');
        $images = (array)Yii::$app->request->post('images');

        // 🔁 Повторная проверка прав (обязательно!)
        if ($entityType === 'offer') {
            $exists = Offers::find()->where(['id' => $entityId, 'vendor_id' => $vendorId])->exists();
        } else {
            $exists = Offers::find()
                ->alias('o')
                ->innerJoin(['s' => 'product_skus'], 's.id = o.sku_id')
                ->andWhere(['o.vendor_id' => $vendorId, 's.global_product_id' => $entityId])
                ->exists();
        }
        if (!$exists) {
            throw new ForbiddenHttpException('Нет доступа');
        }

        // Проверка лимита
        $existingCount = ProductImage::find()
            ->where(['entity_type' => $entityType, 'entity_id' => $entityId])
            ->count();
        if ($existingCount + count($images) > 5) {
            throw new BadRequestHttpException('Превышен лимит в 5 изображений');
        }

        foreach ($images as $img) {
            $image = new ProductImage();
            $image->entity_type = $entityType;
            $image->entity_id = $entityId;
            $image->storage_path = $img['storage_path'];
            $image->filename = $img['filename'] ?? null;
            $image->is_main = true; // по умолчанию
            $image->sort_order = 0;
            if (!$image->save()) {
                throw new Exception('Ошибка сохранения изображения: ' . json_encode($image->errors));
            }
        }

        $offerId = null;

        if ($entityType === 'offer') {
            // Прямая привязка
            $offerId = $entityId;
        } else {
            // global_product → ищем offer ЭТОГО vendor'а для этого товара
            $offer = Offers::find()
                ->where(['vendor_id' => $vendorId])
                ->andWhere(['in', 'sku_id',
                    ProductSkus::find()->select('id')->where(['global_product_id' => $entityId])
                ])
                ->orderBy(['id' => SORT_DESC]) // последний созданный
                ->limit(1)
                ->one();
            $offerId = $offer ? $offer->id : null;
        }

        if ($offerId) {
            Yii::$app->rabbitmq->publishWithRetries(
                RabbitMqController::QUEUE_INDEX,
                [['offer_ids' => [$offerId]]]
            );
        }

        return $this->asJson(['success' => true]);


    }

    public function actionSetMainImage()
    {
        $this->requirePostRequest();
        $vendorId = $this->getAuthorizedVendorId();
        $imageId = (int)Yii::$app->request->post('image_id');

        $image = ProductImage::findOne($imageId);
        if (!$image) {
            throw new NotFoundHttpException();
        }

        // Проверка принадлежности
        if ($image->entity_type === 'offer') {
            $allowed = Offers::find()->where(['id' => $image->entity_id, 'vendor_id' => $vendorId])->exists();
        } else {
            $allowed = Offers::find()
                ->alias('o')
                ->innerJoin(['s' => 'product_skus'], 's.id = o.sku_id')
                ->andWhere(['o.vendor_id' => $vendorId, 's.global_product_id' => $image->entity_id])
                ->exists();
        }
        if (!$allowed) {
            throw new ForbiddenHttpException();
        }

        ProductImage::updateAll(['is_main' => false], [
            'entity_type' => $image->entity_type,
            'entity_id' => $image->entity_id,
        ]);

        $image->is_main = true;
        $image->save(false);

        return $this->asJson(['success' => true]);
    }

    public function actionGetImages()
    {
        $vendorId = $this->getAuthorizedVendorId();
        $entityType = Yii::$app->request->get('entity_type');
        $entityId = (int)Yii::$app->request->get('entity_id');

        if (!in_array($entityType, ['global_product', 'offer'])) {
            throw new BadRequestHttpException('Неверный entity_type');
        }

        // Проверка прав
        if ($entityType === 'offer') {
            $exists = Offers::find()->where(['id' => $entityId, 'vendor_id' => $vendorId])->exists();
        } else {
            $exists = Offers::find()
                ->alias('o')
                ->innerJoin(['s' => 'product_skus'], 's.id = o.sku_id')
                ->andWhere(['o.vendor_id' => $vendorId, 's.global_product_id' => $entityId])
                ->exists();
        }
        if (!$exists) {
            throw new ForbiddenHttpException('Нет доступа');
        }

        $imageRecords = ProductImage::find()
            ->where(['entity_type' => $entityType, 'entity_id' => $entityId])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->asArray()
            ->all();

        $images = [];
        foreach ($imageRecords as $img) {
            $images[] = [
                'id' => $img['id'],
                'storage_path' => $img['storage_path'],
                'filename' => $img['filename'],
                'is_main' => (bool)$img['is_main'],
                'sort_order' => $img['sort_order'],
                'preview_url' => Yii::$app->imageManager->getUrl($img['storage_path'], 120, 120, 'fit')
            ];
        }

        return $this->asJson(['images' => $images]);
    }

    protected function requirePostRequest()
    {
        if (Yii::$app->request->isPost === false) {
            throw new \yii\web\BadRequestHttpException('Только POST-запросы разрешены.');
        }
    }
}