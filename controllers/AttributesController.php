<?php

namespace app\controllers;

use Yii;
use app\models\Attributes;
use yii\helpers\Json;
use yii\rest\Controller;


class AttributesController extends Controller
{
    public function actionIndex()
    {

        $json = Yii::$app->db->createCommand("
    SELECT json_agg(t) 
    FROM (
        SELECT a.id, a.name, a.type, a.is_required,
               COALESCE(json_agg(json_build_object('id', o.id, 'label', o.value)
                        ORDER BY o.sort_order)
                        FILTER (WHERE o.id IS NOT NULL), '[]') AS options
        FROM category_attributes ca
        JOIN attributes a ON a.id = ca.attribute_id
        LEFT JOIN category_attribute_options o ON o.attribute_id = a.id
        WHERE ca.category_id = :cid AND a.status = 1
        GROUP BY a.id, a.name, a.type, a.is_required
        ORDER BY a.id
    ) t
")->bindValue(':cid', 11)
            ->queryScalar();

        return json_decode($json);
    }

}
