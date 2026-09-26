<?php

declare(strict_types=1);

namespace Unwahas\Broadcast\Traits;

use Unwahas\Broadcast\Facades\RabbitMqBroadcast;

trait BroadcastsRabbitMq
{
    /**
     * Broadcast data to a RabbitMQ exchange.
     *
     * @param string $exchange
     * @param string $item
     * @param mixed $data
     * @param string|null $from
     * @return bool
     */
    public function broadcastRabbitMq(
        string $exchange,
        string $item,
        mixed $data,
        ?string $from = null
    ): bool {
        return RabbitMqBroadcast::publish($exchange, $item, $data, $from);
    }
}
