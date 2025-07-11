<?php
namespace app\jobs;

use yii;

class ProductCreateJob implements \yii\queue\JobInterface
{
    public $productData;

    public function execute($queue)
    {
        // Здесь можно добавить логику группировки
        Yii::$app->productService->create($this->productData);
    }
}
