<?php

declare(strict_types=1);

namespace Unwahas\Broadcast\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Unwahas\Broadcast\BroadcastServiceProvider;
use Unwahas\Broadcast\Facades\RabbitMqBroadcast;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            BroadcastServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'RabbitMqBroadcast' => RabbitMqBroadcast::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('rabbitmq_broadcast.connection.host', '127.0.0.1');
        $app['config']->set('rabbitmq_broadcast.connection.port', 5672);
        $app['config']->set('rabbitmq_broadcast.app_name', 'sikawan_test');
    }
}

