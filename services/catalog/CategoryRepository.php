<?php

namespace app\services\catalog;

use app\models\Categories;
use Yii;

class CategoryRepository
{
    /**
     * Обертка над статическим методом findOne для возможности мокирования
     */
    public function findById(int $id): ?Categories
    {
        return Categories::findOne($id);
    }
}
