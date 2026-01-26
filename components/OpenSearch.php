<?php

namespace app\components;


use OpenSearch\Client;
use yii\base\Component;
use OpenSearch\ClientBuilder;
use OpenSearch\Endpoints\Cluster\GetSettings;
use Yii;



class OpenSearch extends Component
{


    public $hosts;
    public $index;

    /**
     * @var \OpenSearch\Client
     */
    private $_client;




    public function init()
    {
        parent::init();
        $hosts = is_array($this->hosts) ? $this->hosts : [$this->hosts];

        $this->_client = ClientBuilder::create()
            ->setHosts($hosts)
            ->build();


    }

    public function getClient()
    {

        return $this->_client;
    }

    // Создание индекса с маппингом
    public function createIndex()
    {
        $params = [
            'index' => $this->index,
            'body' => [
                'settings' => [
                    'index' => [
                        'number_of_shards' => 3,        // настройте под ваш кластер
                        'number_of_replicas' => 1,
                        'refresh_interval' => '30s',    // снижает нагрузку при bulk-загрузке
                        'analysis' => [
                            'analyzer' => [
                                'multilingual' => [
                                    'type' => 'custom',
                                    'tokenizer' => 'standard',
                                    'filter' => ['lowercase','russian_stemmer', 'english_stemmer']
                                ]
                            ],
                            'filter' => [
                                'russian_stemmer' => [
                                    'type' => 'stemmer',
                                    'language' => 'russian'
                                ],
                                'english_stemmer' => [
                                    'type' => 'stemmer',
                                    'language' => 'english'
                                ],

                            ]
                        ]
                    ]
                ],
                'mappings' => [
                    'dynamic' => 'strict', // 🔒 запрещаем случайные поля

                    'properties' => [
                        // === Основные ID ===
                                    // offer ID
                        'product_id' => ['type' => 'integer'],
                        'sku_id' => ['type' => 'keyword'],
                        'vendor_id' => ['type' => 'integer'],

                        // === Текст поиска ===
                        'product_name' => [
                            'type' => 'text',
                            'analyzer' => 'multilingual',
                            'copy_to' => 'full_search',
                            'fields' => [
                                'keyword' => ['type' => 'keyword', 'ignore_above' => 256],

                            ]
                        ],
                        'suggest' => [
                            'type' => 'completion',

                        ],

                        // === Бренд ===
                        'brand_id' => ['type' => 'integer'],
                        'brand_name' => [
                            'type' => 'text',
                            'analyzer' => 'multilingual',
                            'copy_to' => 'full_search',
                            'fields' => [
                                'keyword' => ['type' => 'keyword', 'ignore_above' => 256]
                            ]
                        ],

                        // === Категория ===
                        'category_id' => ['type' => 'integer'],

                        // === Ценовые и складские данные (для сортировки и фильтрации) ===
                        'price' => ['type' => 'scaled_float', 'scaling_factor' => 100],
                        'stock' => ['type' => 'integer'],
                        'condition' => ['type' => 'keyword'], // 'new', 'used', 'refurbished'
                        'warranty' => ['type' => 'integer'],  // месяцы

                        // === Статус и метаданные ===
                        'status' => ['type' => 'keyword'],
                        'is_active' => ['type' => 'boolean'],
                        'vendor_sku' => ['type' => 'keyword'],
                        'sort_order' => ['type' => 'integer'],

                        // === Варианты (EAV атрибуты) — используем nested для точности ===
                        'attributes' => [
                            'type' => 'nested',
                            'properties' => [
                                'attribute_id' => ['type' => 'integer'],
                                'name' => ['type' => 'keyword'],
                                'value' => ['type' => 'keyword'],
                                // Опционально: если есть типы (string/float), можно добавить value_string, value_float и т.д.
                            ]
                        ],

                        // === Плоские атрибуты (опционально, для обратной совместимости) ===
                        'flat_attributes' => [
                            'properties' => [
                                'Цвет' => ['type' => 'keyword'],
                                'Размер' => ['type' => 'keyword'],
                                'weight' => ['type' => 'float']
                            ]
                        ],
                        // === путь к оригиналу в MinIO (для картинок)
                        'image_thumb_key' => ['type' => 'keyword'],

                        // === Единое поле для полнотекстового поиска ===
                        'full_search' => [
                            'type' => 'text',
                            'analyzer' => 'multilingual'
                        ],

                        // === Временные метки ===
                        'created_at' => ['type' => 'date'],
                        'updated_at' => ['type' => 'date'],
                    ]
                ]
            ]
        ];

        return $this->_client->indices()->create($params);
    }


    // Поиск
    public function search($query)
    {
        $params = [
            'index' => $this->index,
            'body' => $query
        ];

        return $this->_client->search($params);
    }

    /**
     * Выполняет bulk-операции (индексацию/удаление)
     *
     * @param array $actions Массив действий в формате OpenSearch bulk API
     * @return array Ответ от OpenSearch
     */
    public function bulk(array $documents)
    {
        try {
            $params = [
                'body' => $documents,
                'refresh' => false // Не обновлять индекс после каждой операции
            ];

            $response = $this->_client->bulk($params);
            Yii::info("Bulk indexed: " . count($documents)/2 . " docs, took: " . ($response['took'] ?? 'n/a') . "ms", 'opensearch');

            if ($response['errors']) {
                $this->logBulkErrors($response);
                throw new \RuntimeException('Bulk operation contains errors');
            }

            return $response;
        } catch (\Exception $e) {
            Yii::error("Bulk error: " . $e->getMessage(), 'opensearch');
            throw $e;
        }
    }


    /**
     * Логирует ошибки из ответа bulk-операции OpenSearch
     *
     * @param array $response Ответ от OpenSearch::bulk()
     */
    protected function logBulkErrors(array $response)
    {
        if (empty($response['items'])) {
            return;
        }
        $errors = [];
        foreach ($response['items'] as $item) {
            $action = array_key_first($item);
            $data = $item[$action];

            if (isset($data['error'])) {
                $errors[] = [
                    'id' => $data['_id'] ?? 'unknown',
                    'error_type' => $data['error']['type'] ?? 'unknown',
                    'reason' => $data['error']['reason'] ?? 'unknown',

                ];
            }
        }

        // Логируем первые 5 ошибок, чтобы не засорять лог
        Yii::error("OpenSearch Bulk Errors (sample): " . json_encode(array_slice($errors, 0, 5), JSON_UNESCAPED_UNICODE), 'opensearch');
    }


    public function deleteIndex()
    {
        try {
            if ($this->_client->indices()->exists(['index' => $this->index])) {
                $this->_client->indices()->delete(['index' => $this->index]);
                Yii::info("Index '{$this->index}' deleted successfully.", 'opensearch');
                return true;
            }
            return false;
        } catch (\Exception $e) {
            Yii::error("Error deleting index '{$this->index}': " . $e->getMessage(), 'opensearch');
            return false;
        }

    }


    /**
     * Проверяет существование индекса
     */
    public function indexExists(): bool
    {
        return $this->_client->indices()->exists(['index' => $this->index]);
    }

    /**
     * Получает количество документов в индексе
     */
    public function getDocumentCount(): int
    {
        if (!$this->indexExists()) {
            return 0;
        }

        $result = $this->_client->count(['index' => $this->index]);
        return $result['count'] ?? 0;
    }

    /**
     * Получает статистику индекса
     */
    public function getIndexStats(): ?array
    {
        if (!$this->indexExists()) {
            return null;
        }

        return $this->_client->indices()->stats(['index' => $this->index]);
    }





}