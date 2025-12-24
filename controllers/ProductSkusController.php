<?php

namespace app\controllers;


use app\models\GlobalProducts;
use app\models\Offers;
use app\models\ProductSkus;
use app\services\ProductSkuVariantHashBuilder;
use app\traits\VendorAuthTrait;
use Yii;
use yii\db\JsonExpression;
use yii\web\BadRequestHttpException;


class ProductSkusController extends \yii\web\Controller
{
    use VendorAuthTrait;

    public function actionCreate()
    {

        $body = Yii::$app->request->getBodyParams();
        $productId = (int)($body['product_id'] ?? 0);
        $values = $body['attributes'] ?? [];
        $code = $body['code'] ?? null;
        $barcode = $body['barcode'] ?? null;
        $status = (int)($body['status'] ?? 1);

        if (!$productId) {
            Yii::$app->response->statusCode = 422;
            return ['error' => 'product_id обязателен'];
        }
        $product = GlobalProducts::findOne(['id' => $productId, 'status' => 10]);
        if (!$product) {
            Yii::$app->response->statusCode = 404;
            return ['error' => 'SPU не найден или неактивен'];
        }
        if (!is_array($values) || !$values) {
            Yii::$app->response->statusCode = 422;
            return ['error' => 'attributes обязателен (массив значений вариантных атрибутов)'];
        }

        [$variantHash]  = (new ProductSkuVariantHashBuilder)->buildVariantHash($values);

        $exists = ProductSkus::find()
            ->where(['global_product_id' => $productId, 'variant_hash' => $variantHash])
            ->exists();

        if ($exists) {
            Yii::$app->response->statusCode = 409;
            return [
                'error' => 'SKU с таким набором вариантных атрибутов уже существует для этого SPU',
                'variant_hash' => $variantHash
            ];
        }

        $sku = new ProductSkus();
        $sku->global_product_id = $productId;
        $sku->variant_hash = $variantHash;
        $sku->variant_values = new JsonExpression($values);
        $sku->barcode = $barcode;
        $sku->status = $status;

        if (!$sku->save()) {
            throw new BadRequestHttpException(json_encode($sku->getErrors(), JSON_UNESCAPED_UNICODE));
        }

        return $this->asJson([
            'id' => $sku->id,
            'product_id' => $sku->global_product_id,
            'variant_hash' => $sku->variant_hash,
        ]);
    }


    public function actionIndex()
    {
        $productId = (int)Yii::$app->request->get('product_id', 0);
        $with = Yii::$app->request->get('with', '');
        $status = Yii::$app->request->get('status', null);
        $limit = min(100, max(1, (int)Yii::$app->request->get('limit', 50)));
        $page = max(1, (int)Yii::$app->request->get('page', 1));

        if (!$productId) {
            Yii::$app->response->statusCode = 422;
            return ['error' => 'product_id обязателен'];
        }

        // Запрос SKU
        $query = ProductSkus::find()->where(['global_product_id' => $productId]);
        if ($status !== null && $status !== '') {
            $query->andWhere(['status' => (int)$status]);
        }

        $total = (int)(clone $query)->count('*');
        $rows = $query
            ->orderBy(['id' => SORT_DESC])
            ->limit($limit)
            ->offset(($page - 1) * $limit)
            ->asArray()
            ->all();

        // 🔥 1. Парсим variant_values из JSON-строки в массив
        foreach ($rows as &$row) {
            $decoded = json_decode($row['variant_values'], true);
            $row['variant_values'] = is_array($decoded) ? $decoded : [];
        }

        // 🔥 2. Подгружаем my_offer, если запрошено
        $loadMyOffer = strpos($with, 'my_offer') !== false;
        if ($loadMyOffer && !empty($rows)) {
            $vendorId = $this->getAuthorizedVendorId(); // ← ваш метод

            if ($vendorId) {
                $skuIds = array_column($rows, 'id');

                // Находим предложения текущего продавца
                $offers = Offers::find()
                    ->select(['vendor_sku','sku_id', 'id', 'price', 'stock', 'warranty', 'condition', 'status'])
                    ->where(['vendor_id' => $vendorId, 'sku_id' => $skuIds])
                    ->indexBy('sku_id')
                    ->asArray()
                    ->all();

                // Добавляем my_offer к каждому SKU
                foreach ($rows as &$row) {
                    $row['my_offer'] = $offers[$row['id']] ?? null;
                }
            }
        }

        return $this->asJson([
            'items' => $rows,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
            ],
        ]);
    }


    public function actionView($id)
    {
        $id = (int)$id;
        if (!$id) {
            Yii::$app->response->statusCode = 400;
            return ['error' => 'ID обязателен'];
        }

        // Загружаем SKU с привязкой к продукту (чтобы можно было проверить категорию/бренд и т.п.)
        $sku = ProductSkus::find()
            ->where(['id' => $id])
            ->with(['globalProduct']) // если есть relation
            ->asArray()
            ->one();

        if (!$sku) {
            Yii::$app->response->statusCode = 404;
            return ['error' => 'SKU не найден'];
        }

        // 🔥 Парсим variant_values из JSON
        $decoded = json_decode($sku['variant_values'], true);
        $sku['variant_values'] = is_array($decoded) ? $decoded : [];

        // 🔥 Опционально: подгружаем my_offer для текущего продавца
        $vendorId = $this->getAuthorizedVendorId();
        if ($vendorId) {
            $offer = Offers::find()
                ->select(['vendor_sku', 'sku_id', 'id', 'price', 'stock', 'warranty', 'condition', 'status'])
                ->where(['vendor_id' => $vendorId, 'sku_id' => $sku['id']])
                ->asArray()
                ->one();

            $sku['my_offer'] = $offer;
        }

        return $this->asJson($sku);
    }




}
