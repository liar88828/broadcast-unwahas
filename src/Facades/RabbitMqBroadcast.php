<?php

declare(strict_types=1);

namespace Unwahas\Broadcast\Facades;

use Illuminate\Support\Facades\Facade;
use Unwahas\Broadcast\Services\RabbitMQService;

/**
 * @method static bool sendDosen(array $data, string $from = 'sikawan', ?string $exchange = null)
 * @method static bool exchange(array $data, ?string $from = null, string $model = 'Default', ?string $exchange = null)
 * @method static bool queue(array $data, ?string $from = null, string $model = 'Default', ?string $queue = null)
 * @method static bool publish(string $exchange, string $model, mixed $data, ?string $from = null)
 *
 * @see \Unwahas\Broadcast\Services\RabbitMQService
 */
class RabbitMqBroadcast extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return RabbitMQService::class;
    }
}
