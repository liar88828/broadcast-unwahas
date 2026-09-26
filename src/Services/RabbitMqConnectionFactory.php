<?php

declare(strict_types=1);

namespace Unwahas\Broadcast\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;

class RabbitMqConnectionFactory
{
    /**
     * Create an AMQPStreamConnection from config array or Laravel config.
     *
     * @param array<string, mixed>|null $config
     */
    public static function create(?array $config = null): AMQPStreamConnection
    {
        // Support config('rabbitmq_broadcast.connection') or fallback to config('rabbitmq')
        $cfg = $config ?? config('rabbitmq_broadcast.connection') ?? config('rabbitmq') ?? [];

        $host = (string) ($cfg['host'] ?? '127.0.0.1');
        $port = (int) ($cfg['port'] ?? 5672);
        $user = (string) ($cfg['user'] ?? 'guest');
        $password = (string) ($cfg['password'] ?? 'guest');
        $vhost = (string) ($cfg['vhost'] ?? '/');
        $insist = (bool) ($cfg['insist'] ?? false);
        $loginMethod = (string) ($cfg['login_method'] ?? 'AMQPLAIN');
        $loginResponse = $cfg['login_response'] ?? null;
        $locale = (string) ($cfg['locale'] ?? 'en_US');
        $connectionTimeout = (float) ($cfg['connection_timeout'] ?? 3.0);
        $heartbeat = (int) ($cfg['heartbeat'] ?? 30);
        $readWriteTimeout = (int) ($cfg['read_write_timeout'] ?? ($heartbeat * 2));
        $keepalive = (bool) ($cfg['keepalive'] ?? true);

        // Ensure read_write_timeout is at least 2x heartbeat
        if ($heartbeat > 0 && $readWriteTimeout < ($heartbeat * 2)) {
            $readWriteTimeout = $heartbeat * 2;
        }

        return new AMQPStreamConnection(
            $host,
            $port,
            $user,
            $password,
            $vhost,
            $insist,
            $loginMethod,
            $loginResponse,
            $locale,
            $connectionTimeout,
            $readWriteTimeout,
            null,
            $keepalive,
            $heartbeat
        );
    }
}
