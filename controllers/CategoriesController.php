<?php

namespace app\controllers;
use app\models\Attributes;
use Yii;
use app\models\Categories;
use app\models\Brands;
use app\models\BrandCategory;
use yii\web\Controller;

class CategoriesController extends Controller
{
    public function actionIndex($parent_id = null)
    {
        $query = Yii::$app->db->createCommand("
            SELECT id, name , is_leaf
            FROM {{%categories}}
            WHERE " . ($parent_id === null ? "parent_id IS NULL" : "parent_id = :pid") . "
            ORDER BY name
        ");

        if ($parent_id !== null) {
            $query->bindValue(":pid", (int)$parent_id);
        }

        $rows = $query->queryAll();

        return $this->asJson($rows);
    }

    public function actionBrands($id)
    {
        try {
            // Проверяем существование категории
            $category = Categories::findOne($id);
            if (!$category) {
                Yii::$app->response->statusCode = 404;
                return $this->asJson(['error' => 'Категория не найдена']);
            }


            $cacheKey = "cat:{$id}:brands:v1";
            $data = Yii::$app->cache->getOrSet(
                $cacheKey,
                function () use ($id) {
                    return (new \yii\db\Query())
                        ->select(['id' => 'b.id', 'name' => 'b.name'])
                        ->from(['b' => Brands::tableName()])
                        ->innerJoin(['bc' => BrandCategory::tableName()], 'bc.brand_id = b.id')
                        ->where([
                            'bc.category_id' => $id,
                            'b.status' => Brands::STATUS_ACTIVE,
                        ])
                        ->orderBy(['b.name' => SORT_ASC])
                        ->all();
                },
                3600,
                new \yii\caching\TagDependency(['tags' => ["category-brands:$id", 'brands']])
            );

            return $this->asJson([
                'data' => $data,
                'totalCount' => count($data),
                'category' => [
                    'id' => $category->id,
                    'name' => $category->name,
                ],
            ]);
        } catch (\Throwable $e) {
            Yii::$app->response->statusCode = 500;
            return $this->asJson(['error' => 'Ошибка сервера: ' . $e->getMessage()]);
        }


    }

    public function actionView($id)
    {
        $row = Yii::$app->db->createCommand("
        SELECT id, name, is_leaf
        FROM {{%categories}}
        WHERE id = :id
        LIMIT 1
    ")->bindValue(':id', (int)$id)
            ->queryOne();

        if (!$row) {
            throw new \yii\web\NotFoundHttpException('Category not found');
        }

        return $this->asJson($row);
    }

}