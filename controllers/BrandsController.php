<?php

namespace app\controllers;

use app\models\Brands;
use yii\web\Controller;
use yii\data\ActiveDataProvider;
use yii;
use yii\web\UploadedFile;
use app\models\BrandCategory;

class BrandsController extends Controller
{




    public function actionCreate()
    {
        $model = new Brands();
        $model->load(Yii::$app->request->post(), '');

        $file = UploadedFile::getInstanceByName('logo');
        if ($file) {
            $dir = Yii::getAlias('@webroot/uploads/brands');
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $fileName = uniqid('brand_') . '.' . $file->extension;
            if ($file->saveAs($dir . DIRECTORY_SEPARATOR . $fileName)) {
                $model->logo = '/uploads/brands/' . $fileName;
            }
        }

        if ($model->save()) {

            $categoryId = Yii::$app->request->post('category_id');
            if ($categoryId) {
                $this->attachBrandToCategory($model->id, $categoryId);
            }

            Yii::$app->response->setStatusCode(201);
            return $this->asJson($model);
        }

        Yii::$app->response->setStatusCode(422);
        return $this->asJson($model->getErrors());
    }


    /**
     * Привязка бренда к категории
     */
    private function attachBrandToCategory($brandId, $categoryId)
    {
        // Проверяем, не существует ли уже такая связь
        $existing = BrandCategory::find()
            ->where(['brand_id' => $brandId, 'category_id' => $categoryId])
            ->exists();

        if (!$existing) {
            $brandCategory = new BrandCategory();
            $brandCategory->brand_id = $brandId;
            $brandCategory->category_id = $categoryId;
            return $brandCategory->save();
        }

        return true;
    }



    public function actionList($categoryId)
    {
        $categoryId = (int)$categoryId;
        if ($categoryId <= 0) {
            Yii::$app->response->setStatusCode(400);
            return ['error' => 'Invalid category ID.'];
        }

        $search = Yii::$app->request->get('search');
        // Очистка и валидация search
        if (is_string($search)) {
            $search = trim($search);
            if (strlen($search) < 2) { // Минимум 2 символа для поиска
                $search = null;
            }
        }

        // Жесткий лимит для выпадающего списка
        $limit = 100;

        // Попытка использовать кэш для запросов без поиска
        if (empty($search) && Yii::$app->cache) {
            $cacheKey = "brands_cat_{$categoryId}_v1";
            $brands = Yii::$app->cache->get($cacheKey);
            if ($brands !== false) {
                return $brands;
            }
        }

        $query = Brands::find()
            ->select(['id', 'name'])
            ->innerJoin('brand_category', 'brand_category.brand_id = brand.id')
            ->andWhere(['brand_category.category_id' => $categoryId]);

        if (!empty($search)) {
            $query->andWhere(['ilike', 'brand.name', $search]);
        }

        $query->limit($limit);
        $brands = $query->asArray()->all();

        // Кэшируем только если поиск не использовался
        if (empty($search) && Yii::$app->cache) {
            Yii::$app->cache->set($cacheKey, $brands, 3600); // 1 час
        }

        return  $this->asJson($brands);
    }

}
