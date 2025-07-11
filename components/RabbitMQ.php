<?php

namespace app\components;


use yii\base\Component;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Yii;

class RabbitMQ extends Component
{

    public $host;
    public $port;
    public $user;
    public $password;
    public $vhost;

    /**
     * @var AMQPStreamConnection
     */
    private $connection;
    private $channels = [];



    public function init()
    {
        parent::init();
        try {
            $this->connection = new AMQPStreamConnection(
                $this->host,
                $this->port,
                $this->user,
                $this->password,
                $this->vhost,

            );
        } catch (\Exception $e) {
            \Yii::error("RabbitMQ connection failed: " . $e->getMessage());
            throw $e;
        }

    }


    public function getConnection()
    {
        return $this->connection;
    }


    protected function reconnect()
    {
        try {
            // Закрываем всё корректно перед переподключением
            $this->closeConnection();

            // Создаем новое подключение
            $this->connection = new AMQPStreamConnection(
                $this->host,
                $this->port,
                $this->user,
                $this->password,
                $this->vhost
            // можно добавить таймауты и heartbeat при необходимости
            );

            Yii::info("Успешное переподключение к RabbitMQ");
        } catch (\Exception $e) {
            Yii::error("Ошибка переподключения к RabbitMQ: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Получение канала по идентификатору. Если канал не создан или закрыт – создаёт новый.
     *
     * @param string $channelId (например, 'default', 'publisher', 'consumer')
     * @return \PhpAmqpLib\Channel\AMQPChannel
     */
    public function getChannel($channelId = 'default')
    {
        if (!$this->connection || !$this->connection->isConnected()) {
            $this->reconnect();
        }

        if (!isset($this->channels[$channelId]) || !$this->channels[$channelId]->is_open()) {
            $this->channels[$channelId] = $this->getConnection()->channel();
        }
        return $this->channels[$channelId];
    }


    /**
     * Объявляет очередь с DLX политикой для повторных попыток
     */
    public function declareRetryQueue($queueName, $retryExchange, $retryRoutingKey, $maxRetries = 3, $delay = 10000)
    {
        $channel = $this->getChannel('setup');

        // Аргументы для основной очереди
        $args = new \PhpAmqpLib\Wire\AMQPTable([
            'x-dead-letter-exchange' => $retryExchange,
            'x-dead-letter-routing-key' => $retryRoutingKey,
            'x-max-priority' => 10 // Поддержка приоритетов
        ]);

        // Объявляем основную очередь
        $channel->queue_declare(
            $queueName,
            false,  // passive
            true,   // durable
            false,  // exclusive
            false,  // auto_delete
            false,  // nowait
            $args
        );

        // Аргументы для retry очереди
        $retryArgs = new \PhpAmqpLib\Wire\AMQPTable([
            'x-dead-letter-exchange' => '',
            'x-dead-letter-routing-key' => $queueName,
            'x-message-ttl' => $delay, // Задержка перед повторной попыткой
            'x-max-priority' => 10
        ]);

        // Объявляем retry очередь
        $retryQueueName = $queueName . '.retry';
        $channel->queue_declare(
            $retryQueueName,
            false,  // passive
            true,   // durable
            false,  // exclusive
            false,  // auto_delete
            false,  // nowait
            $retryArgs
        );

        // Объявляем обменник для retry
        $channel->exchange_declare(
            $retryExchange,
            'direct',
            false,  // passive
            true,   // durable
            false   // auto_delete
        );

        // Привязываем retry очередь к обменнику
        $channel->queue_bind($retryQueueName, $retryExchange, $retryRoutingKey);
    }


    /**
     * Создание сообщения
     * → Упаковываем данные + настраиваем свойства доставки.
     *
     * Активация подтверждений
     * → Говорим RabbitMQ: "Жду ответа, что ты получил сообщение".
     *
     * Публикация
     * → Отправляем сообщение в очередь.
     *
     * Ожидание подтверждения
     * → Ждем ответа от RabbitMQ: "Сообщение получено" (или ошибку).
     *
     */
    public function publishMessageWithRetry($queueName, $messages, $headers = [], $priority = 0)
    {
        $channel = $this->getChannel('publish');
        $channel->confirm_select();//режим подтверждений для канала снижает производительность ,но гарантирует, что сообщения не потеряются при сбоях
        // Подготавливаем все сообщения заранее
        $preparedMessages = array_map(function($message) use ($headers, $priority) {
            return new AMQPMessage(
                json_encode($message),
                [
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                    'priority' => $priority,
                    'application_headers' => new \PhpAmqpLib\Wire\AMQPTable($headers)
                ]
            );
        }, $messages);

        // Публикуем пачкой
        foreach ($preparedMessages as $msg) {
            $channel->batch_basic_publish($msg, '', $queueName);
        }

        $channel->publish_batch();

    }

    /**
     * метод повторной переотправки если оборвется связь с каналом
     */
    public function publishWithRetries($queueName, $messages, $headers = [], $priority = 0)
    {
        if (empty($messages)) {
            \Yii::warning("publishWithRetries called with empty message array.", __METHOD__);
            return;
        }

        $maxRetries = 3;
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                $this->publishMessageWithRetry($queueName, $messages, $headers, $priority);
                return;
            } catch (\Throwable $e) {
                $attempt++;
                \Yii::error("Publish attempt #$attempt failed: " . $e->getMessage(), __METHOD__);

                if ($attempt >= $maxRetries) {
                    throw new \RuntimeException("Failed to publish message after $maxRetries retries. Last error: " . $e->getMessage(), 0, $e);
                }
            }
        }

    }




    public function consumeWithRetry($queueName, callable $callback, $prefetchCount = 30)
    {
        $channel = $this->getChannel('consume');

        // QoS для контроля нагрузки
        $channel->basic_qos(null, $prefetchCount, null);

        $channel->basic_consume(
            $queueName,
            '',    // consumer tag
            false, // no_local
            false, // no_ack
            false, // exclusive
            false, // nowait
            function (AMQPMessage $msg) use ($callback) {
                try {
                    // Вызываем пользовательский callback
                    $result = $callback($msg);

                    if ($result !== false) {
                        $msg->ack();
                    } else {
                        // Если callback вернул false, отправляем сообщение в retry очередь
                        $msg->nack(false);
                    }
                } catch (\Throwable $e) {
                    Yii::error("Error processing message: " . $e->getMessage());
                    $msg->nack(false); // Отправляем в retry очередь
                }
            }
        );

        try {
            while ($channel->is_consuming()) {
                $channel->wait(null, false, 6.0);
            }
        } catch (\Throwable $e) {
            Yii::error("Consume error: " . $e->getMessage());
            throw $e;
        }
    }





        public function closeConnection()
        {
            foreach ($this->channels as $channel) {
                if ($channel->is_open()) {
                    try {
                        $channel->close();
                    } catch (\Exception $e) {
                        Yii::error("Channel close error: " . $e->getMessage());
                    }
                }
            }
            $this->channels = [];
            if ($this->connection && $this->connection->isConnected()) {
                $this->connection->close();
            }
        }




}