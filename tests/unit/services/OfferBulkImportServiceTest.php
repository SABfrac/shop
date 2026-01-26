<?php

namespace app\tests\unit\services;

use Codeception\Test\Unit;
use app\models\Categories;
use app\services\catalog\AttributeService;
use app\services\catalog\BrandService;
use app\services\catalog\DataNormalizerService;
use app\services\catalog\GlobalProductService;
use app\services\catalog\OfferService;
use app\services\catalog\SkuService;
use app\services\catalog\CategoryRepository;
use app\services\ProductSkuVariantHashBuilder;
use app\services\offer\OfferBulkImportService;
use Yii;
use yii\base\Component;

/**
 * запуск теста: docker-compose exec php vendor/bin/codecept run unit services/OfferBulkImportServiceTest
 */

class OfferBulkImportServiceTest extends Unit
{
    // Моки сервисов
    private $brandServiceMock;
    private $globalProductServiceMock;
    private $skuServiceMock;
    private $offerServiceMock;
    private $attributeServiceMock;
    private $dataNormalizerServiceMock;
    private $hashBuilderMock;
    private $categoryRepositoryMock; // <-- Мок репозитория

    protected function _before()
    {
        // Создаем моки для всех зависимостей
        $this->brandServiceMock = $this->createStub(BrandService::class);
        $this->globalProductServiceMock = $this->createMock(GlobalProductService::class);
        $this->skuServiceMock = $this->createStub(SkuService::class);
        $this->offerServiceMock = $this->createMock(OfferService::class);
        $this->attributeServiceMock = $this->createStub(AttributeService::class);
        $this->dataNormalizerServiceMock = $this->createStub(DataNormalizerService::class);
        $this->hashBuilderMock = $this->createStub(ProductSkuVariantHashBuilder::class);
        $this->categoryRepositoryMock = $this->createMock(CategoryRepository::class);

        // Мокаем Redis (глобальный компонент)
        $redisMock = $this->getMockBuilder(\yii\redis\Connection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['executeCommand'])
            ->getMock();
        $redisMock->method('executeCommand')->willReturn(true);
        Yii::$app->set('redis', $redisMock);
    }

    protected function _after()
    {
        Yii::$app->clear('redis');
    }

    public function testImportChunkSuccess()
    {
        // 1. DTO / Входные данные
        $vendorId = 1;
        $categoryId = 555;
        $reportId = 10;
        $rows = [
            [
                'sku_code' => 'SKU-001',
                'product_name' => 'Super Phone',
                'price' => 999.99,
                'stock' => 50,
                'brand' => 'Samsung'
            ]
        ];

        $dummyCategory = $this->getMockBuilder(Categories::class)
            ->disableOriginalConstructor()
            ->getMock();

        $dummyCategory->id = $categoryId;
        $dummyCategory->name = 'Смартфоны';


        $this->categoryRepositoryMock->expects($this->once())
            ->method('findById')
            ->with($categoryId)
            ->willReturn($dummyCategory);

        // B. Атрибуты (валидация)
        $this->attributeServiceMock->method('getCachedVariantAttributes')->willReturn([]);
        $this->attributeServiceMock->method('mapFeedAttributesToStructured')
            ->willReturn(['forHash' => [], 'forStorage' => []]);

        // C. Hash и Normalizer
        $this->hashBuilderMock->method('buildVariantHash')->willReturn(['hash_xyz']);
        $this->dataNormalizerServiceMock->method('normalizer')->willReturn('samsung');
        $this->dataNormalizerServiceMock->method('buildMatchKeyForGlobalProduct')->willReturn('key_samsung_phone');

        // D. Brands
        $this->brandServiceMock->method('ensureBrands')->willReturn(['samsung' => 10]);

        // E. Global Products
        $this->globalProductServiceMock->method('preloadGlobalProducts')->willReturn([
            'by_match_key' => [], 'by_gtin' => [], 'by_model_brand' => [], 'by_canonical_name_cat' => []
        ]);
        $this->globalProductServiceMock->method('bulkInsertGlobalProducts')->willReturn(['key_samsung_phone' => 700]);

        // F. Skus
        $this->skuServiceMock->method('preloadSkus')->willReturn([]);
        $this->skuServiceMock->method('bulkInsertSkus')->willReturn(['700|hash_xyz' => 800]);

        // G. Offers (Главная цель теста - проверить, что дошли досюда)
        $this->offerServiceMock->expects($this->once())
            ->method('upsertOffers')
            ->willReturn([12345]); // Вернули ID созданного оффера

        // 3. СОЗДАНИЕ СЕРВИСА
        $service = new OfferBulkImportService(
            $this->brandServiceMock,
            $this->globalProductServiceMock,
            $this->skuServiceMock,
            $this->offerServiceMock,
            $this->attributeServiceMock,
            $this->dataNormalizerServiceMock,
            $this->hashBuilderMock,
            $this->categoryRepositoryMock,
            []
        );

        // 4. ЗАПУСК
        $result = $service->importChunk($vendorId, $rows, $categoryId, $reportId);

        // 5. ПРОВЕРКИ
        $this->assertEquals(1, $result['success']);
        $this->assertEquals([12345], $result['offer_ids']);
    }

    public function testImportChunkCategoryNotFound()
    {
        $categoryId = 999;

        // Настраиваем репозиторий вернуть null
        $this->categoryRepositoryMock->expects($this->once())
            ->method('findById')
            ->with($categoryId)
            ->willReturn(null);

        $service = new OfferBulkImportService(
            $this->brandServiceMock,
            $this->globalProductServiceMock,
            $this->skuServiceMock,
            $this->offerServiceMock,
            $this->attributeServiceMock,
            $this->dataNormalizerServiceMock,
            $this->hashBuilderMock,
            $this->categoryRepositoryMock,
            []
        );

        // Ожидаем Exception (или возврат ошибки, зависит от вашей обработки)
        // В вашем коде: throw new \InvalidArgumentException("Категория... не найдена");
        // Блок try-catch внутри importChunk ловит это и возвращает success => 0

        $result = $service->importChunk(1, [['sku_code' => '1']], $categoryId, 1);

        $this->assertEquals(0, $result['success']);
        $this->assertStringContainsString('не найдена', $result['errors']['global']);
    }

    /**
     * @test
     * @covers \app\services\offer\OfferBulkImportService::validateAndNormalizeRows
     */
    public function testImportChunkFailsOnMissingSkuCode()
    {
        $categoryId = 555;
        $rows = [['product_name' => 'Phone', 'price' => 100, 'stock' => 10, 'brand' => 'X']];


        $dummyCategory =  $this->createMock(Categories::class);
        $dummyCategory->id = $categoryId;
        $dummyCategory->name = 'Тест';
        $this->categoryRepositoryMock->expects($this->once())
            ->method('findById')
            ->with($categoryId)
            ->willReturn($dummyCategory);




        $service = new OfferBulkImportService(
            $this->brandServiceMock,
            $this->globalProductServiceMock,
            $this->skuServiceMock,
            $this->offerServiceMock,
            $this->attributeServiceMock,
            $this->dataNormalizerServiceMock,
            $this->hashBuilderMock,
            $this->categoryRepositoryMock,
            []
        );


        $result = $service->importChunk(1, $rows, 555, 1);
        $this->assertEquals(0, $result['success']);
        $this->assertStringContainsString('Требуется vendor_sku', $result['errors'][0]);
    }

    public function testImportChunkDeduplicatesIdenticalRows()
    {
        $vendorId = 1;
        $categoryId = 555;
        $reportId = 10;

        // Две ОДИНАКОВЫЕ строки с одинаковым sku_code
        $rows = [
            [
                'sku_code' => 'PHONE-001',
                'product_name' => 'Super Phone',
                'price' => 999.99,
                'stock' => 50,
                'brand' => 'Samsung'
            ],
            [
                'sku_code' => 'PHONE-001', // ← тот же артикул!
                'product_name' => 'Super Phone',
                'price' => 999.99,
                'stock' => 50,
                'brand' => 'Samsung'
            ]
        ];

        // ===== Моки =====
        $dummyCategory =  $this->createMock(Categories::class);
        $dummyCategory->id = $categoryId;
        $dummyCategory->name = 'Смартфоны';
        $this->categoryRepositoryMock->expects($this->once())
            ->method('findById')
            ->with($categoryId)
            ->willReturn($dummyCategory);

        // Атрибуты
        $this->attributeServiceMock->method('getCachedVariantAttributes')->willReturn([]);
        $this->attributeServiceMock->method('mapFeedAttributesToStructured')
            ->willReturn(['forHash' => [], 'forStorage' => []]);

        // Хеши и нормализация
        $this->hashBuilderMock->method('buildVariantHash')->willReturn(['hash_xyz']);
        $this->dataNormalizerServiceMock->method('normalizer')->willReturn('samsung');
        $this->dataNormalizerServiceMock->method('buildMatchKeyForGlobalProduct')
            ->willReturn('key_samsung_phone');



        // Бренды
        $this->brandServiceMock->method('ensureBrands')->willReturn(['samsung' => 10]);

        // Global Products: имитируем, что GP ещё не существует
        $this->globalProductServiceMock->method('preloadGlobalProducts')
            ->willReturn([
                'by_match_key' => [],
                'by_gtin' => [],
                'by_model_brand' => [],
                'by_canonical_name_cat' => []
            ]);
        // При вставке вернём один GP для match_key
        $this->globalProductServiceMock->method('bulkInsertGlobalProducts')
            ->willReturn(['key_samsung_phone' => 700]);

        // Skus: имитируем, что SKU не существует
        $this->skuServiceMock->method('preloadSkus')->willReturn([]);
        $this->skuServiceMock->method('bulkInsertSkus')
            ->willReturn(['700|hash_xyz' => 800]);

        // Offers: ожидаем ОДИН вызов upsertOffers с ОДНИМ оффером
        $this->offerServiceMock->expects($this->once())
            ->method('upsertOffers')
            ->with($this->callback(function ($offers) {
                // Проверяем, что передан ровно 1 оффер
                if (count($offers) !== 2) {
                    return false;
                }
                foreach ($offers as $offer) {
                    if ($offer['vendor_sku'] !== 'phone-001' || $offer['sku_id'] !== 800) {
                        return false;
                    }
                }
                return true;
            }))
            ->willReturn([12345]);

        // ===== Запуск =====
        $service = new OfferBulkImportService(
            $this->brandServiceMock,
            $this->globalProductServiceMock,
            $this->skuServiceMock,
            $this->offerServiceMock,
            $this->attributeServiceMock,
            $this->dataNormalizerServiceMock,
            $this->hashBuilderMock,
            $this->categoryRepositoryMock,
            []
        );

        $result = $service->importChunk($vendorId, $rows, $categoryId, $reportId);



        // ===== Проверки =====
        $this->assertEquals(2, $result['success']); // обе строки валидны
        $this->assertCount(1, $result['offer_ids']); // но создан только 1 оффер
        $this->assertEquals([12345], $result['offer_ids']);
    }



}
