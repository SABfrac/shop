<?php

namespace app\controllers;
use yii\helpers\Json;
use Yii;
use yii\rest\Controller;

class CategoriesAttributesController extends Controller
{
    public function actionView($id)
    {
        $json = Yii::$app->db->createCommand("
        SELECT json_build_object(
            'variant_attributes', COALESCE((
                SELECT json_agg(t)
                FROM (
                    SELECT 
                        a.id, a.name, a.type, a.is_required, ca.is_variant,
                        COALESCE(
                            json_agg(
                                json_build_object('id', o.id, 'label', o.value) 
                                ORDER BY o.sort_order
                            ) FILTER (WHERE o.id IS NOT NULL),
                            '[]'::json
                        ) AS options
                    FROM category_attributes ca
                    JOIN attributes a ON a.id = ca.attribute_id
                    LEFT JOIN category_attribute_options o 
                        ON o.attribute_id = a.id 
                        AND o.category_id = :cid  
                    WHERE 
                        ca.category_id = :cid 
                       AND ca.is_variant = TRUE 
                        AND a.status = 1
                    GROUP BY a.id, a.name, a.type, a.is_required, ca.is_variant
                    ORDER BY a.id
                ) t
            ), '[]'::json),
            'spec_attributes', COALESCE((
                SELECT json_agg(t)
                FROM (
                    SELECT 
                        a.id, a.name, a.type, a.is_required, ca.is_variant,
                        COALESCE(
                            json_agg(
                                json_build_object('id', o.id, 'label', o.value) 
                                ORDER BY o.sort_order
                            ) FILTER (WHERE o.id IS NOT NULL),
                            '[]'::json
                        ) AS options
                    FROM category_attributes ca
                    JOIN attributes a ON a.id = ca.attribute_id
                    LEFT JOIN category_attribute_options o 
                        ON o.attribute_id = a.id 
                        AND o.category_id = :cid  
                    WHERE 
                        ca.category_id = :cid 
                       AND ca.is_variant = FALSE 
                        AND a.status = 1
                    GROUP BY a.id, a.name, a.type, a.is_required, ca.is_variant
                    ORDER BY a.id
                ) t
            ), '[]'::json)
        ) AS data;
    ")->bindValue(':cid', $id)
            ->queryScalar();

        $data = $json ? json_decode($json, true) : null;
        return $data ?: ['variant_attributes' => [], 'spec_attributes' => []];
    }


}
