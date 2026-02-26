<?php

namespace app\controllers;
use app\traits\VendorAuthTrait;
use yii\web\Controller;
use app\models\Offers;
use app\models\ProductSkus;
use app\commands\RabbitMqController;
use app\components\RabbitMQ\AmqpTopology as AMQP;
use Yii;
use yii\helpers\ArrayHelper;

class OffersController  extends Controller
{
    use VendorAuthTrait;


    /**
     * GET offers/view
     */

    public function actionView()
    {
        $id = Yii::$app->request->get('id');
        try {
            $vendorId = $this->getAuthorizedVendorId();
        } catch (\Exception $e) {
            throw new \yii\web\ForbiddenHttpException('Не авторизован как продавец');
        }

        $offer = Offers::find()
            ->where(['id' => $id, 'vendor_id' => $vendorId])
            ->with([
                'sku.globalProduct.category',  // Добавляем категорию
                'sku.globalProduct.brand'      // Добавляем бренд
            ])
            ->one();

        if (!$offer) {
            throw new \yii\web\NotFoundHttpException('Оффер не найден');
        }

        $product = $offer->sku->globalProduct ?? null;

        $data = [
            'id' => $offer->id,
            'vendor_sku' => (string)$offer->vendor_sku,
            'price' => (float)$offer->price,
            'stock' => (int)$offer->stock,
            'warranty' => $offer->warranty !== null ? (int)$offer->warranty : null,
            'status' => (int)$offer->status,
            'condition' => $offer->condition ?? 'new',

            // Информация о товаре (только для отображения)
            'product_name' => $product?->canonical_name ?? '',
            'category_name' => $product?->category?->name ?? '',
            'brand_name' => $product?->brand?->name ?? null,
            'gtin' => $product?->gtin ?? null,

            // Вариант SKU
            'variant_label' => $this->formatVariantLabel($offer->sku->variant_values ?? []),

            // Полные данные SKU (если нужны)
            'sku' => $offer->sku ? [
                'id' => $offer->sku->id,
                'code' => $offer->sku->code ?? null,
                'variant_values' => $offer->sku->variant_values,
            ] : null,
        ];

        return $this->asJson(['success' => true, 'data' => $data]);
    }

    /**
     * POST /offers/save
     */
    public function actionSave()
    {
        $vendorId = $this->getAuthorizedVendorId();
        $data = Yii::$app->request->getBodyParams();

        // Поддержка как массива, так и одиночного объекта
        if (!is_array($data)) {
            Yii::$app->response->statusCode = 400;
            return $this->asJson(['success' => false, 'error' => 'Неверный формат данных']);
        }

        // Если передан одиночный объект (есть ключ 'id' или 'sku_id' на верхнем уровне)
        if (isset($data['id']) || isset($data['sku_id'])) {
            $data = [$data];
        }

        if (empty($data)) {
            return $this->asJson(['success' => true, 'message' => 'Нет данных для обработки']);
        }

        $errors = [];
        $savedIds = [];

        foreach ($data as $index => $item) {
            $offer = null;

            // РЕЖИМ РЕДАКТИРОВАНИЯ: если передан id оффера
            if (!empty($item['id'])) {
                $offer = Offers::find()
                    ->where(['id' => $item['id'], 'vendor_id' => $vendorId])
                    ->one();

                if (!$offer) {
                    $errors[] = "Элемент $index: Оффер с id {$item['id']} не найден или нет доступа";
                    continue;
                }
            }
            // РЕЖИМ СОЗДАНИЯ: нужен sku_id
            else {
                if (!isset($item['sku_id'])) {
                    $errors[] = "Элемент $index: sku_id обязателен для создания";
                    continue;
                }

                // Проверяем существование SKU
                $sku = ProductSkus::findOne($item['sku_id']);
                if (!$sku) {
                    $errors[] = "Элемент $index: SKU с id {$item['sku_id']} не найден";
                    continue;
                }

                // Ищем существующий оффер или создаём новый
                $offer = Offers::find()
                    ->where(['vendor_id' => $vendorId, 'sku_id' => $item['sku_id']])
                    ->one();

                if (!$offer) {
                    $offer = new Offers();
                    $offer->sku_id = $item['sku_id'];
                    $offer->vendor_id = $vendorId;
                }
            }

            // Заполняем поля (только если они переданы)
            if (array_key_exists('vendor_sku', $item)) {
                $offer->vendor_sku = $item['vendor_sku'] ?: self::generate();
            } elseif ($offer->isNewRecord) {
                $offer->vendor_sku = self::generate();
            }

            if (array_key_exists('price', $item)) {
                $offer->price = (float)$item['price'];
            }

            if (array_key_exists('stock', $item)) {
                $offer->stock = (int)$item['stock'];
            }

            if (array_key_exists('warranty', $item)) {
                $offer->warranty = $item['warranty'] !== null ? (int)$item['warranty'] : null;
            }

            if (array_key_exists('condition', $item)) {
                $offer->condition = $item['condition'] ?? 'new';
            }

            if (array_key_exists('status', $item)) {
                // Защита: нельзя самому снять с модерации
                $newStatus = (int)$item['status'];
                if (!($offer->status == 2 && $newStatus == 1)) {
                    $offer->status = $newStatus;
                }
            }

            if (array_key_exists('sort_order', $item)) {
                $offer->sort_order = (int)$item['sort_order'];
            }

            // Валидация и сохранение
            if (!$offer->validate()) {
                foreach ($offer->errors as $attribute => $messages) {
                    foreach ($messages as $message) {
                        $errors[] = "Элемент $index: $attribute — $message";
                    }
                }
                continue;
            }

            if (!$offer->save(false)) {
                $errors[] = "Элемент $index: ошибка сохранения в БД";
                continue;
            }

            Yii::$app->redis->setex(
                "offer:$offer->id",
                3600,
                json_encode([
                    'price' => $offer->price,
                    'stock' => $offer->stock,

                ]),

            );

            if ($offer->id) {
                Yii::$app->rabbitmq->publishWithRetries(
                   '',
                    [
                        ['offer_ids' => [$offer->id]]

                    ],
                    RAMQP::QUEUE_INDEX,

                );
            }



            $savedIds[] = $offer->id;
        }

        if (!empty($errors)) {
            Yii::$app->response->statusCode = 422;
            return $this->asJson([
                'success' => false,
                'message' => 'Ошибки при сохранении',
                'errors' => $errors,
                'saved_ids' => $savedIds
            ]);
        }

        return $this->asJson([
            'success' => true,
            'message' => count($savedIds) === 1 ? 'Оффер сохранён' : 'Офферы сохранены',
            'saved_ids' => $savedIds,
            'count' => count($savedIds)
        ]);
    }


    public function actionDelete($id)
    {
        $vendorId = $this->getAuthorizedVendorId();

        $offer = Offers::findOne(['id' => $id, 'vendor_id' => $vendorId]);

        if (!$offer) {
            \Yii::$app->response->setStatusCode(404);
            return $this->asJson(['error' => 'Предложение не найдено или у вас нет прав на его удаление.']);
        }

        //  можно проверить статус или другие условия (например, нельзя удалить, если есть активные заказы)
        // if ($offer->hasActiveOrders()) {
        //     \Yii::$app->response->setStatusCode(400);
        //     return ['error' => 'Нельзя удалить предложение с активными заказами.'];
        // }

        if ($offer->delete()) {


            Yii::$app->redis->del("offer:$offer->id");

            // Отправляем в очередь индексации — bulkIndexOffers поймёт, что оффера нет в БД → удалит из индекса
            Yii::$app->rabbitmq->publishWithRetries(
                '',
                [
                    ['offer_ids' => [$offer->id]]
                ],
                AMQP::QUEUE_INDEX,
            );

            \Yii::$app->response->setStatusCode(204); // No Content — стандарт для успешного DELETE
            return $this->asJson([]); // Тело ответа не требуется при 204
        } else {
            \Yii::$app->response->setStatusCode(500);
            return $this->asJson(['error' => 'Не удалось удалить предложение. Попробуйте позже.']);
        }

    }

    public static function generate(): string
    {
        $id = Yii::$app->db->createCommand("SELECT nextval('sku_code_seq')")->queryScalar();
        $base36 = strtoupper(base_convert((string)$id, 10, 36));
        return 'SK-' . str_pad($base36, 6, '0', STR_PAD_LEFT);
    }


/**
 * Форматирует variant_values в читаемую строку
*/
    private function formatVariantLabel($variantValues): ?string
    {
        if (empty($variantValues) || !is_array($variantValues)) {
            return null;
        }

        $parts = [];
        foreach ($variantValues as $item) {
            if (isset($item['name']) && isset($item['value'])) {
                $parts[] = $item['name'] . ': ' . $item['value'];
            }
        }

        return !empty($parts) ? implode(', ', $parts) : null;
    }
}