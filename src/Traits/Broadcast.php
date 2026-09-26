<?php

declare(strict_types=1);

namespace Unwahas\Broadcast\Traits;

use Unwahas\Broadcast\Facades\RabbitMqBroadcast;

trait Broadcast
{
    use BroadcastsRabbitMq;

    /**
     * Optional custom consumer definitions if defined inside a consumer class or command.
     * If not overridden, consumer configuration will be loaded from config('rabbitmq_broadcast.consumers').
     *
     * @var list<array{
     *     route: string,
     *     exchange: string,
     *     queue: string,
     *     item: array<string, array{0: string, 1: string}>
     * }>|null
     */
    public ?array $consumers = null;

    /**
     * Helper to broadcast data to RabbitMQ.
     *
     * @param string $exchange
     * @param string $model
     * @param mixed $data
     * @param string|null $from
     * @return bool
     */
    public function broadcast(
        string $exchange,
        string $model,
        mixed $data,
        ?string $from = null
    ): bool {
        return RabbitMqBroadcast::publish($exchange, $model, $data, $from);
    }
}
