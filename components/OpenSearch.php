<?php

namespace app\components;


use OpenSearch\Client;
use yii\base\Component;
use OpenSearch\ClientBuilder;
use Yii;
use app\traits\ProductDataPreparer;


class OpenSearch extends Component
{

    use ProductDataPreparer;
    public $hosts;
    public $index;

    /**
     * @var \OpenSearch\Client
     */
    private $_client;




    public function init()
    {
        parent::init();
        $this->_client= ClientBuilder::create()
            ->setHosts($this->hosts)
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
                'mappings' => [
                    'dynamic'=> 'strict', // Запрещаем автоматическое создание полей
                    'dynamic_templates' => [
                        [
                            'strings_as_keywords' => [
                                'match_mapping_type' => 'string',
                                'mapping' => [
                                    'type' => 'keyword'  // Все новые строковые поля → keyword
                                ]
                            ]
                        ]
                    ],

                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'name' => [
                            'type' => 'text',
                            'copy_to' => 'full_search',
                            'fields' => [
                                'keyword' => ['type' => 'keyword'],
                                'suggest' => ['type' => 'completion']
                            ]
                        ],
                        'description' => [
                            'type' => 'text',
                            'copy_to' => 'full_search'
                        ],
                        'price' => ['type' => 'float'],
                        'category' => [
                            'properties' => [
                                'id' => ['type' => 'integer'],
                                'name' => ['type' => 'keyword']
                            ]
                        ],
                        'brand' => [
                            'properties' => [
                                'id' => ['type' => 'integer'],
                                'name' => [
                                    'type' => 'text', // Для полнотекстового поиска по названию бренда
                                    'fields' => [
                                        'keyword' => ['type' => 'keyword'] // Для точной фильтрации
                                    ]
                                ]
                            ]
                        ],
                        'attributes' => [
                            'type' => 'nested',
                            'properties' => [
                                'id' => ['type' => 'integer'],
                                'name' => ['type' => 'keyword'],
                                'value' => ['type' => 'keyword']
                            ]
                        ],
                        'flat_attributes' => [
                            'properties' => [
                                'color' => ['type' => 'keyword'],
                                'size' => ['type' => 'keyword'],
                                'weight' => ['type' => 'float']
                            ]
                        ],
                        'full_search' => [
                            'type' => 'text'],
                    ]
                ]
            ]
        ];

        return $this->_client->indices()->create($params);
    }

    // Индексация продукта
    public function indexProduct($product)
    {
        $params = [
            'index' => $this->index,
            'id' => $product->id,
            'body' => $this->prepareProductData($product)
        ];

        return $this->_client->index($params);
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
                'index' => $this->index,
                'body' => $documents,
                'refresh' => false // Не обновлять индекс после каждой операции
            ];

            $response = $this->_client->bulk($params);

            if ($response['errors']) {
                $this->logBulkErrors($response);
                throw new \RuntimeException('Bulk operation contains errors');
            }

            return $response;
        } catch (\Exception $e) {
            Yii::error("Bulk error: " . $e->getMessage());
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

        foreach ($response['items'] as $item) {
            // Проверяем наличие ошибок в каждой операции
            $operation = reset($item); // index/delete/update
            if (!empty($operation['error'])) {
                $errors[] = [
                    'type' => key($item),
                    'id' => $operation['_id'],
                    'error_type' => $operation['error']['type'] ?? null,
                    'status' => $operation['status']
                ];
            }
        }
        if (!empty($errors)) {
            Yii::error([
                'message' => 'Bulk errors occurred',
                'errors_sample' => array_slice($errors, 0, 5),
                'errors_count' => count($errors)
            ], 'opensearch_bulk_errors');
        }
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




}