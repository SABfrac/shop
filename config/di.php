<?php

namespace app\config;
use yii\di\Instance;
use app\services\catalog\AttributeService;
use app\services\catalog\BrandService;
use app\services\catalog\DataNormalizerService;
use app\services\catalog\GlobalProductService;
use app\services\catalog\OfferService;
use app\services\catalog\SkuService;
use app\services\ProductSkuVariantHashBuilder;
use app\jobs\OpensearchIndexer;
use app\queue\handlers\IndexMessageHandler;

return [
    'container' => [
        'definitions' => [
            BrandService::class => BrandService::class,
            GlobalProductService::class => GlobalProductService::class,
            SkuService::class => SkuService::class,
            OfferService::class => OfferService::class,
            AttributeService::class => AttributeService::class,
            DataNormalizerService::class => DataNormalizerService::class,
            ProductSkuVariantHashBuilder::class => ProductSkuVariantHashBuilder::class,
            OpensearchIndexer::class => [
                '__class' => OpensearchIndexer::class,
                '__construct()' => [
                    Instance::of(DataNormalizerService::class),
                ],
            ],
            IndexMessageHandler::class => IndexMessageHandler::class,
        ],

    ],
];