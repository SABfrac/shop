<?php
namespace app\jobs;


use yii\base\BaseObject;
use yii\queue\JobInterface;
use app\models\Products;
use yii;
use yii\caching\TagDependency;
use app\traits\ProductDataPreparer;

class PrepareAndSyncProductJob extends BaseObject implements JobInterface
{
    const CACHE_TAGS_TEMPLATE = [
        'product:core:{id}',
        'product:category:{id}',
        'product:brand:{id}',
        'product:flat:{id}',
        'product:attributes:{id}',
    ];


    use ProductDataPreparer;
    public $productIds;
    public $operation;

    public function execute($queue)
    {
        if ($this->operation === 'delete') {

            foreach ($this->productIds as $productId) {
                $this->invalidateAllCaches($productId);
                Yii::$app->searchSynchronizer->syncProduct(['id' => $productId], 'delete');
            }
            return;
        }

        $product = $this->loadProductWithRelations();//AR данные(обьект)
        if (!$product) return;
        $productData = $this->prepareAndCacheProductData($product);

        // Кешируй объединённый, если legacy-места ещё используют
        Yii::$app->cache->set("product:full:{$product->id}", $productData, 3600, new TagDependency([
            'tags' => ["product:full:{$product->id}"]
        ]));

        Yii::$app->searchSynchronizer->syncProduct($productData, $this->operation);
    }

    protected function loadProductWithRelations()
    {
        {
            return Products::find()
                ->with([
                    'vendor',
                    'category',
                    'brand',
                    'productAttributeValues.attributeOption',
                    'productFlat',
                    'productAttributeValues.productAttributes'
                ])
                ->where(['id' => $this->productIds])
                ->all();
        }

    }

    public static function invalidateAllCaches($productId)
    {
        $tags = array_map(
            fn($template) => str_replace('{id}', $productId, $template),
            self::CACHE_TAGS_TEMPLATE
        );

        TagDependency::invalidate(Yii::$app->cache, $tags);
    }




}
