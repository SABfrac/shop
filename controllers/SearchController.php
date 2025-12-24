<?php

namespace app\controllers;
use Yii;
use yii\web\Response;

class SearchController extends \yii\web\Controller
{

    public function actionSearch()
    {


        $query = Yii::$app->request->get('query', '');
        $page = (int)Yii::$app->request->get('page', 1);
        $limit = (int)Yii::$app->request->get('limit', 20);

        // Защита от перегрузки
        $limit = min($limit, 100);

        $from = ($page - 1) * $limit;

        // Формируем запрос к OpenSearch
        $searchParams = [
            'index' => Yii::$app->opensearch->index,
            'body' => [
                'from' => $from,
                'size' => $limit,
                'query' => [
                    'bool' => [
                        'must' => [
                            [
                                'multi_match' => [
                                    'query' => $query,
                                    'fields' => ['full_search^3', 'product_name^2', 'brand_name'],
                                    'type' => 'best_fields',
                                    'operator' => 'and' // или 'or'
                                ]
                            ]
                        ],
                        'filter' => [
                            ['term' => ['status' => 1]],     // только активные
                            ['range' => ['stock' => ['gte' => 1]]] // только в наличии
                        ]
                    ]
                ],
                'highlight' => [
                    'fields' => [
                        'product_name' => new \stdClass(),
                        'brand_name' => new \stdClass()
                    ]
                ],
                'sort' => ['sort_order' => 'asc', 'price' => 'asc'] // или ваша логика
            ]
        ];

        // Убираем must, если запрос пустой (показываем все)
        if (empty(trim($query))) {
            unset($searchParams['body']['query']['bool']['must']);
        }

        $result = Yii::$app->opensearch->getClient()->search($searchParams);

        // Формируем ответ для фронта
        $hits = array_map(function ($hit) {
            $source = $hit['_source'];
            return [
                'id' => $source['id'],
                'product_name' => $source['product_name'],
                'brand_name' => $source['brand_name'],
                'price' => $source['price'],
                'stock' => $source['stock'],
                'attributes' => $source['attributes'] ?? [],
                'highlight' => $hit['highlight'] ?? null,
            ];
        }, $result['hits']['hits']);

        return $this->asJson([
            'items' => $hits,
            'total' => $result['hits']['total']['value'],
            'page' => $page,
            'limit' => $limit
        ]);
    }



    public function actionSearchProducts()
    {

        $query = trim(Yii::$app->request->get('query', ''));
        $categoryId = (int)Yii::$app->request->get('category_id', 0);
        $brandId = (int)Yii::$app->request->get('brand_id', 0);
        $limit = min((int)Yii::$app->request->get('limit', 20), 100);
        $rawCursor = Yii::$app->request->get('cursor');
        $cursor = is_string($rawCursor) ? trim($rawCursor) : '';


        if ($query === '' && $categoryId === 0 && $brandId === 0) {
            return $this->asJson([
                'items' => [],
                'total' => 0,
                'next_cursor' => null,
            ]);
        }

        // Флаг: нужны ли полные данные? По умолчанию - НЕТ.
        // Если фронту вдруг понадобятся все поля, он передаст &full=1
        $isFullInfo = (int)Yii::$app->request->get('full', 0);

        // 1. Определяем, какие поля доставать из OpenSearch
        // Если нужны только карточки — берем минимум.
        $fieldsToReturn = [
            'id', 'product_id', 'product_name', 'brand_name', 'category_id',
            'sku_id', 'vendor_sku','price', 'stock'  // Добавьте сюда картинку, если есть
        ];


        if ($isFullInfo) {
            $fieldsToReturn = ['*']; // Или null, чтобы вернуть всё
        }

        $searchBody = [
            'size' => $limit,
            '_source' => $fieldsToReturn,
            'sort' => [
                ['updated_at' => ['order' => 'desc']],
                ['_id' => ['order' => 'desc']], // _id всегда уникален
            ],
            'query' => [
                'bool' => [
                    'filter' => [],
                    'must' => []
                ]
            ],
            // Ограничиваем точность total (ускоряет запрос)
            'track_total_hits' => 10000,
        ];

        if ($cursor !== '') {
            $parts = explode(',', $cursor);
            if (count($parts) === 2) {
                // Приводим timestamp к int, _id остаётся строкой
                $searchBody['search_after'] = [(int)$parts[0], $parts[1]];
            }
        }

        // === 5. Применяем фильтры ===
        if ($categoryId > 0) {
            $searchBody['query']['bool']['filter'][] = ['term' => ['category_id' => $categoryId]];
        }
        if ($brandId > 0) {
            $searchBody['query']['bool']['filter'][] = ['term' => ['brand_id' => $brandId]];
        }
        if ($query !== '') {
            $searchBody['query']['bool']['must'][] = [
                'multi_match' => [
                    'query' => $query,
                    'fields' => ['full_search^3', 'product_name^2', 'sku_id^5'],
                    'type' => 'best_fields',
                    'operator' => 'and',
                    'analyzer' => 'multilingual',
                ]
            ];
        }

        // === 6. Выполняем запрос к OpenSearch ===
        try {
            $result = Yii::$app->opensearch->getClient()->search([
                'index' => 'products',
                'body' => $searchBody,
            ]);
        } catch (\Exception $e) {
            \Yii::error('OpenSearch search error: ' . $e->getMessage(), 'search');
            throw new HttpException(500, 'Ошибка поиска');
        }

        // === 7. Преобразуем результаты ===
        $items = [];
        $hits = $result['hits']['hits'] ?? [];

        foreach ($hits as $hit) {
            $source = $hit['_source'] ?? [];
            // OpenSearch _id — строка, но в БД это int
            $source['id'] = (int)$hit['_id'];
            $items[] = $source;
        }

        // === 8. Обогащаем данными из Redis (живые price/stock) ===
        if (!empty($items)) {
            $ids = array_column($items, 'id');
            $redisKeys = array_map(fn($id) => "offer:$id", $ids);

            try {
                $redisValues = Yii::$app->redis->mget(...$redisKeys);
                $liveData = [];
                foreach ($redisValues as $i => $val) {
                    if ($val !== false && $val !== null) {
                        $liveData[$ids[$i]] = json_decode($val, true);
                    }
                }

                foreach ($items as &$item) {
                    if (isset($liveData[$item['id']])) {
                        $live = $liveData[$item['id']];
                        // Обновляем только если есть данные
                        if (isset($live['price'])) $item['price'] = $live['price'];
                        if (isset($live['stock'])) $item['stock'] = $live['stock'];
                    }
                }
                unset($item);
            } catch (\Exception $e) {
                \Yii::warning('Redis fallback failed: ' . $e->getMessage(), 'search');
                // Не прерываем — продолжаем с данными из OpenSearch
            }
        }

        // === 9. Генерируем next_cursor для фронтенда ===
        $nextCursor = null;
        if (!empty($hits)) {
            $lastHit = end($hits);
            if (isset($lastHit['sort']) && is_array($lastHit['sort']) && count($lastHit['sort']) === 2) {
                $nextCursor = implode(',', $lastHit['sort']);
            }
        }

        // === 10. Возвращаем ответ ===
        return $this->asJson([
            'items' => $items,
            'total' => min((int)($result['hits']['total']['value'] ?? 0), 10000),
            'next_cursor' => $nextCursor,
        ]);
    }

    public function actionSuggest()
    {

        $query = mb_strtolower(trim(Yii::$app->request->get('q', '')), 'UTF-8');
        if (strlen($query) < 2) {
            return $this->asJson([]);
        }

        try {
            $result = Yii::$app->opensearch->getClient()->search([
                'index' => 'products',
                'body' => [
                    // Нам не нужны _source и hits — только suggestions
                    '_source' => false,
                    'size' => 0, // отключаем обычные результаты
                    'suggest' => [
                        'product-suggest' => [
                            'prefix' => $query,
                            'completion' => [
                                'field' => 'suggest',
                                'size' => 8,
                                'skip_duplicates' => true,
                            ]
                        ]
                    ]
                ]
            ]);

            $suggestions = [];
            if (!empty($result['suggest']['product-suggest'][0]['options'])) {
                foreach ($result['suggest']['product-suggest'][0]['options'] as $option) {
                    // $option['text'] — это значение из 'input'
                    $suggestions[] = $option['text'];
                }
            }

            return $this->asJson(array_values(array_unique($suggestions)));

        } catch (\Exception $e) {
            Yii::error('Suggest failed: ' . $e->getMessage(), 'opensearch');
            return $this->asJson([]);
        }
    }
}