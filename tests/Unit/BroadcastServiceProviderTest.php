<?php

declare(strict_types=1);

namespace Unwahas\Broadcast\Tests\Unit;

use Unwahas\Broadcast\Facades\RabbitMqBroadcast;
use Unwahas\Broadcast\Services\RabbitMQService;
use Unwahas\Broadcast\Services\RabbitMqPublisher;
use Unwahas\Broadcast\Tests\TestCase;

class BroadcastServiceProviderTest extends TestCase
{
    public function test_config_is_merged(): void
    {
        $this->assertNotEmpty(config('rabbitmq_broadcast.connection'));
        $this->assertSame('127.0.0.1', config('rabbitmq_broadcast.connection.host'));
        $this->assertSame(5672, config('rabbitmq_broadcast.connection.port'));
    }

    public function test_rabbitmq_service_is_registered_as_singleton(): void
    {
        $service1 = $this->app->make(RabbitMQService::class);
        $service2 = $this->app->make(RabbitMQService::class);

        $this->assertInstanceOf(RabbitMQService::class, $service1);
        $this->assertSame($service1, $service2);
    }

    public function test_aliases_are_registered(): void
    {
        $serviceByAlias = $this->app->make(RabbitMqPublisher::class);
        $serviceByName = $this->app->make('rabbitmq.broadcast');
        $singleton = $this->app->make(RabbitMQService::class);

        $this->assertSame($singleton, $serviceByAlias);
        $this->assertSame($singleton, $serviceByName);
    }

    public function test_facade_resolves_instance(): void
    {
        $this->assertInstanceOf(RabbitMQService::class, RabbitMqBroadcast::getFacadeRoot());
    }
}

