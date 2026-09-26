<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | RabbitMQ Connection Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for connecting to the RabbitMQ broker.
    |
    */
    'connection' => [
        'host' => env('RABBITMQ_HOST', '127.0.0.1'),
        'port' => (int) env('RABBITMQ_PORT', 5672),
        'user' => env('RABBITMQ_USER', 'guest'),
        'password' => env('RABBITMQ_PASSWORD', 'guest'),
        'vhost' => env('RABBITMQ_VHOST', '/'),
        'heartbeat' => (int) env('RABBITMQ_HEARTBEAT', 30),
        'connection_timeout' => (float) env('RABBITMQ_CONN_TIMEOUT', 3.0),
        'read_write_timeout' => (int) env('RABBITMQ_RW_TIMEOUT', 60),
        'keepalive' => (bool) env('RABBITMQ_KEEPALIVE', true),
        'insist' => false,
        'login_method' => 'AMQPLAIN',
        'login_response' => null,
        'locale' => 'en_US',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Sender Identifier
    |--------------------------------------------------------------------------
    |
    | Identifier attached as the 'from' field in message payloads when publishing.
    |
    */
    'app_name' => env('RABBITMQ_APP_NAME', env('APP_NAME', 'laravel_app')),

    /*
    |--------------------------------------------------------------------------
    | Consumer Settings & Defaults
    |--------------------------------------------------------------------------
    */
    'consumer' => [
        // Prefetch count per consumer
        'prefetch_count' => (int) env('RABBITMQ_PREFETCH_COUNT', 1),

        // Whether to re-queue messages on failure (false = drop/dead-letter, true = retry)
        'requeue_on_failure' => (bool) env('RABBITMQ_REQUEUE_ON_FAILURE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Consumer Route Definitions
    |--------------------------------------------------------------------------
    |
    | Queue names must be unique per consuming application — two different
    | services binding the same queue name to the same fanout exchange will
    | compete for messages instead of each getting their own copy.
    |
    | 'item' maps a model name (from message payload) to a [Service::class, 'method']
    | or callable, resolved via Laravel Service Container at dispatch time.
    |
    */
    'consumers' => [
        [
            'route' => 'sikawan',
            'exchange' => env('RABBITMQ_EXCHANGE_SIKAWAN', 'laravel_exchange_sikawan'),
            'queue' => env('RABBITMQ_QUEUE_SIKAWAN', 'laravel_queue_dosen_sikawan'),
            'item' => [
                // 'Biodata' => [\App\Services\DosenService::class, 'updateDosenConsumer'],
            ],
        ],
        [
            'route' => 'simawa',
            'exchange' => env('RABBITMQ_EXCHANGE_SIMAWA', 'laravel_exchange_simawa'),
            'queue' => env('RABBITMQ_QUEUE_SIMAWA', 'laravel_queue_sikawan_simawa'),
            'item' => [
                // 'Mahasiswa' => [\App\Services\MahasiswaService::class, 'updateMahasiswaConsumer'],
            ],
        ],
    ],
];
