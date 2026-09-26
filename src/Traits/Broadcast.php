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
     *     from: string,
     *     exchange: string,
     *     queue: string,
     *     items: array<string, array{0: string, 1: string}>
     * }>|null
     */
    public ?array $consumers = null;

    /**
     * Helper to broadcast data to RabbitMQ.
     *
     * @param string $exchange
     * @param string $item
     * @param mixed $data
     * @param string|null $from
     * @return bool
     */
    public function broadcast(
        string $exchange,
        string $item,
        mixed $data,
        ?string $from = null
    ): bool {
        return RabbitMqBroadcast::publish($exchange, $item, $data, $from);
    }
}
