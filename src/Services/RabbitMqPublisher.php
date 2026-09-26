<?php

declare(strict_types=1);

namespace Unwahas\Broadcast\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

class RabbitMqPublisher
{
    private ?AMQPStreamConnection $connection = null;

    /**
     * Get or create active AMQP connection.
     */
    protected function getConnection(): AMQPStreamConnection
    {
        if ($this->connection === null || !$this->connection->isConnected()) {
            $this->connection = RabbitMqConnectionFactory::create();
        }

        return $this->connection;
    }

    /**
     * Standard broadcast sender untuk data Dosen (Biodata).
     *
     * @param array{
     *     id: string|int,
     *     email: string,
     *     nama: string,
     *     gelar_depan?: string|null,
     *     gelar_belakang?: string|null,
     *     sent_at?: string|null
     * } $data
     * @param string $from Identitas sistem pengirim (default: 'sikawan')
     * @param string|null $exchange Nama exchange (default: dari config)
     * @return bool
     */
    public function sendDosen(array $data, string $from = 'sikawan', ?string $exchange = null): bool
    {
        $payloadData = [
            'id'             => $data['id'] ?? null,
            'email'          => $data['email'] ?? null,
            'nama'           => $data['nama'] ?? null,
            'gelar_depan'    => $data['gelar_depan'] ?? null,
            'gelar_belakang' => $data['gelar_belakang'] ?? null,
            'sent_at'        => $data['sent_at'] ?? date('Y-m-d H:i:s'),
        ];

        return $this->exchange(
            data: $payloadData,
            from: $from,
            item: 'Biodata',
            exchange: $exchange
        );
    }

    /**
     * Broadcast data ke Fanout Exchange (semua queue yang bind akan menerima copy pesan).
     *
     * @param array<string, mixed> $data
     * @param string|null $from
     * @param string $item
     * @param string|null $exchange
     * @return bool
     */
    public function exchange(
        array $data,
        ?string $from = null,
        string $item = 'Default',
        ?string $exchange = null
    ): bool {
        $exchangeName = $exchange
            ?? config('rabbitmq_broadcast.exchange')
            ?? config('rabbitmq.exchange')
            ?? 'laravel_exchange_' . ($from ?? 'broadcast');

        $sender = $from ?? config('rabbitmq_broadcast.app_name', config('app.name', 'sikawan'));

        $channel = null;

        try {
            $channel = $this->getConnection()->channel();

            $channel->exchange_declare(
                $exchangeName,
                'fanout',
                false,
                true,
                false
            );

            $payload = [
                'data' => $data,
                'from' => $sender,
                'item' => $item,
            ];

            $messageBody = json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            $amqpMessage = new AMQPMessage($messageBody, [
                'content_type'  => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            ]);

            $channel->basic_publish($amqpMessage, $exchangeName, '');

            return true;
        } catch (Throwable $e) {
            report($e);
            return false;
        } finally {
            $channel?->close();
        }
    }

    /**
     * Direct publish ke Queue spesifik.
     *
     * @param array<string, mixed> $data
     * @param string|null $from
     * @param string $item
     * @param string|null $queue
     * @return bool
     */
    public function queue(
        array $data,
        ?string $from = null,
        string $item = 'Default',
        ?string $queue = null
    ): bool {
        $queueName = $queue
            ?? config('rabbitmq_broadcast.queue')
            ?? config('rabbitmq.queue')
            ?? 'laravel_queue_' . ($from ?? 'broadcast');

        $sender = $from ?? config('rabbitmq_broadcast.app_name', config('app.name', 'sikawan'));

        $channel = null;

        try {
            $channel = $this->getConnection()->channel();

            $channel->queue_declare(
                $queueName,
                false,
                true,
                false,
                false
            );

            $payload = [
                'data' => $data,
                'from' => $sender,
                'item' => $item,
            ];

            $messageBody = json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            $amqpMessage = new AMQPMessage($messageBody, [
                'content_type'  => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            ]);

            $channel->basic_publish($amqpMessage, '', $queueName);

            return true;
        } catch (Throwable $e) {
            report($e);
            return false;
        } finally {
            $channel?->close();
        }
    }

    /**
     * Publish a broadcast message (flexible signature).
     *
     * @param string $exchange
     * @param string $item
     * @param mixed $data
     * @param string|null $from
     * @param string $routingKey
     * @return bool
     */
    public function publish(
        string $exchange,
        string $item,
        mixed $data,
        ?string $from = null,
        string $routingKey = ''
    ): bool {
        return $this->exchange(
            data: (array) $data,
            from: $from,
            item: $item,
            exchange: $exchange
        );
    }

    public function __destruct()
    {
        if ($this->connection !== null && $this->connection->isConnected()) {
            try {
                $this->connection->close();
            } catch (Throwable) {
                // Ignore errors during destructor cleanup
            }
        }
    }
}
