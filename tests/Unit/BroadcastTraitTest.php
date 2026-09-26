<?php

declare(strict_types=1);

namespace Unwahas\Broadcast\Tests\Unit;

use Unwahas\Broadcast\Facades\RabbitMqBroadcast;
use Unwahas\Broadcast\Tests\TestCase;
use Unwahas\Broadcast\Traits\Broadcast;
use Unwahas\Broadcast\Traits\BroadcastsRabbitMq;

class DummyBroadcastClass
{
    use Broadcast;
}

class DummyBroadcastsRabbitMqClass
{
    use BroadcastsRabbitMq;
}

class BroadcastTraitTest extends TestCase
{
    public function test_broadcast_trait_calls_facade_publish(): void
    {
        RabbitMqBroadcast::shouldReceive('publish')
            ->once()
            ->with('exchange_test', 'Biodata', ['id' => 10], 'sikawan')
            ->andReturn(true);

        $instance = new DummyBroadcastClass();
        $result = $instance->broadcast('exchange_test', 'Biodata', ['id' => 10], 'sikawan');

        $this->assertTrue($result);
    }

    public function test_broadcasts_rabbitmq_trait_calls_facade_publish(): void
    {
        RabbitMqBroadcast::shouldReceive('publish')
            ->once()
            ->with('exchange_test', 'Mahasiswa', ['id' => 20], 'simawa')
            ->andReturn(true);

        $instance = new DummyBroadcastsRabbitMqClass();
        $result = $instance->broadcastRabbitMq('exchange_test', 'Mahasiswa', ['id' => 20], 'simawa');

        $this->assertTrue($result);
    }
}

