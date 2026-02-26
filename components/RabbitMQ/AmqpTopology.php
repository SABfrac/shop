<?php
namespace app\components\RabbitMQ;

/**
 * Централизованное описание топологии RabbitMQ
 *
 *
 * Использование:
 *   use app\components\rabbitmq\AmqpTopology as AMQP;
 *   AMQP::QUEUE_PARSE
 *   AMQP::RK_FEED_CHUNK_PROCESSED
 */
final class AmqpTopology
{
    // ======================
    // ОЧЕРЕДИ ЗАДАЧ
    // ======================
    public const QUEUE_PARSE = 'feed.parse';
    public const QUEUE_PROCESS = 'feed.process';
    public const QUEUE_INDEX = 'feed.index';
    public const QUEUE_INDEX_DLQ = 'feed.index.dlq'; // Dead Letter Queue

    // ======================
    // СОБЫТИЙНАЯ ТОПОЛОГИЯ
    // ======================
    // Обменники
    public const EXCHANGE_EVENTS = 'events_direct'; // Тип: direct

    // Очереди слушателей
    public const QUEUE_METRICS_LISTENER = 'queue_metrics_listener';
    public const QUEUE_INDEXING_LISTENER = 'queue_indexing_listener';
    public const QUEUE_FINALIZATION_LISTENER = 'queue_finalization_listener';

    // Routing Keys ( используются для маршрутизации)
    public const RK_FEED_CHUNK_PROCESSED = 'feed.chunk.processed';
    public const RK_FEED_FINALIZED = 'feed.finalized';

}
