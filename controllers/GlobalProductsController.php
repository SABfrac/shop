<?php

namespace app\controllers;

use app\models\GlobalProducts;
use app\models\Offers;
use app\models\GlobalProductsSearch;
use yii\rest\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use app\models\ProductAttributeValues;
use app\helper\DataNormalizer;
use yii;

/**
 * ProductsController implements the CRUD actions for GlobalProducts model.
 */
class GlobalProductsController extends Controller
{

    public function actionCreate()
    {
        $body = Yii::$app->request->getRawBody();
        $data = json_decode($body, true);

        $transaction = Yii::$app->db->beginTransaction();

        try {
            // 1. PRODUCTS
            Yii::$app->db->createCommand()->insert('{{%products}}', [
                'name' => $data['product']['name'],
                'description' => $data['product']['description'] ?? null,
                'brand_id' => $data['product']['brand_id'] ?? null,
                'category_id' => $data['category_id'],
                'slug' => $data['product']['slug'] ?? null,
                'status' => $data['product']['status'] ?? 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ])->execute();

            $productId = Yii::$app->db->getLastInsertID();

            // 2. ATTRIBUTES (EAV)
            foreach ($data['product_attribute_values'] as $attr) {
                $insert = [
                    'product_id' => $productId,
                    'attribute_id' => $attr['attribute_id'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];

                if (isset($attr['value_string'])) {
                    $insert['value_string'] = $attr['value_string'];
                }
                if (isset($attr['value_int'])) {
                    $insert['value_int'] = $attr['value_int'];
                }
                if (isset($attr['value_float'])) {
                    $insert['value_float'] = $attr['value_float'];
                }
                if (isset($attr['value_bool'])) {
                    $insert['value_bool'] = $attr['value_bool'];
                }
                if (isset($attr['attribute_option_id'])) {
                    $insert['attribute_option_id'] = $attr['attribute_option_id'];
                }

                Yii::$app->db->createCommand()->insert('{{%product_attribute_values}}', $insert)->execute();
            }

            // 3. OFFERS
            foreach ($data['offers'] as $offer) {
                Yii::$app->db->createCommand()->insert('{{%offers}}', [
                    'product_id' => (int)$productId,
                    'vendor_id' => Yii::$app->user->id, // например, текущий продавец
                    'price' => (float)$offer['price'],
                    'stock' => (int)$offer['stock'],
                    'sku' => $offer['sku'] ?? null,
                    'condition' => $offer['condition'],
                    'status' => $offer['status'] ?? false,
                    'sort_order' => $offer['sort_order'] ?? 0,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ])->execute();
            }

            $transaction->commit();
            return ['success' => true, 'product_id' => $productId];

        } catch (\Throwable $e) {
            $transaction->rollBack();
            Yii::error($e->getMessage(), __METHOD__);
            Yii::$app->response->statusCode = 500;
            return $this->asJson( ['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function actionIndex()
    {
        $request    = Yii::$app->request;
        $categoryId = $request->get('category_id');
        $brandId    = $request->get('brand_id')??null;
        $q          = trim((string)$request->get('q', ''));
        $limit      = min(100, max(1, (int)$request->get('limit', 10)));
        $page       = max(1, (int)$request->get('page', 1));

        if ($categoryId === null || $brandId === null) {
            Yii::$app->response->statusCode = 422;
            return  $this->asJson(['error' => 'category_id и brand_id обязательны']);
        }

        $query = GlobalProducts::find()
            ->where([
                'category_id' => (int)$categoryId,
                'brand_id'    => (int)$brandId,
                'status'      => 10, // активно
            ]);

        if ($q !== '') {
            $query->andWhere(['or',
                ['like', 'canonical_name', $q],
                ['like', 'slug', $q],
            ]);
        }

        $total = (int)(clone $query)->count('*');

        $rows = $query
            ->select(['id', 'canonical_name', 'slug', 'description', 'category_id', 'brand_id'])
            ->orderBy(['canonical_name' => SORT_ASC, 'id' => SORT_ASC])
            ->limit($limit)
            ->offset(($page - 1) * $limit)
            ->asArray()
            ->all();

        return $this->asJson([
            'items' => $rows,
            'meta'  => [
                'page'  => $page,
                'limit' => $limit,
                'total' => $total,
            ],
        ]);
    }

    public function actionView(int $id)
    {

        $p = GlobalProducts::find()
            ->select(['id', 'canonical_name', 'slug', 'description', 'category_id', 'brand_id', 'status'])
            ->where(['id' => $id])
            ->asArray()
            ->one();

        if (!$p) {
            throw new NotFoundHttpException('Продукт не найден');
        }

        return $this->asJson($p) ;
    }


}
