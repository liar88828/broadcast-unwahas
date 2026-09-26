<?php

declare(strict_types=1);

namespace Unwahas\Broadcast\Tests\Unit;

use PhpAmqpLib\Exception\AMQPIOParseError;
use PhpAmqpLib\Exception\AMQPIOException;
use Throwable;
use Unwahas\Broadcast\Services\RabbitMqConnectionFactory;
use Unwahas\Broadcast\Tests\TestCase;

class RabbitMqConnectionFactoryTest extends TestCase
{
    public function test_create_attempts_connection_with_expected_config(): void
    {
        // Because 127.0.0.1:5672 might not be running a real broker in unit test environment,
        // calling create() will attempt connection to the specified host/port and throw connection exception.
        // We verify that it attempts to connect using the configured host and port.
        try {
            RabbitMqConnectionFactory::create([
                'host' => '127.0.0.1',
                'port' => 59999, // Unused port
                'connection_timeout' => 0.1,
            ]);
            $this->fail('Expected connection failure on invalid port.');
        } catch (Throwable $e) {
            $this->assertTrue(
                $e instanceof AMQPIOException || $e instanceof AMQPIOParseError || str_contains($e->getMessage(), '59999') || str_contains($e->getMessage(), 'Connection refused') || str_contains($e->getMessage(), 'actively refused') || str_contains($e->getMessage(), 'stream_socket_client')
            );
        }
    }
}

