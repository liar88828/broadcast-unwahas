<?php

declare(strict_types=1);

namespace Unwahas\Broadcast\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPBasicCancelException;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use PhpAmqpLib\Message\AMQPMessage;
use RuntimeException;
use Throwable;
use Unwahas\Broadcast\Services\RabbitMqConnectionFactory;
use Unwahas\Broadcast\Traits\Broadcast;

class RabbitMqConsumeCommand extends Command
{
    use Broadcast;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbitmq:consume
                            {--from= : Specific sender name to consume (default: all configured consumers)}
                            {--route= : (Alias for --from) Specific route/sender name to consume}
                            {--requeue : Requeue messages on processing failure}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consume messages from RabbitMQ queues and process handler actions';

    /**
     * Resolved consumers list for execution.
     *
     * @var list<array{
     *     from?: string,
     *     route?: string,
     *     exchange: string,
     *     queue: string,
     *     items?: array<string, array{0: string, 1: string}|string|callable>,
     *     model?: array<string, array{0: string, 1: string}|string|callable>,
     *     item?: array<string, array{0: string, 1: string}|string|callable>
     * }>
     */
    protected array $activeConsumers = [];

    public function handle(): int
    {
        $this->resolveConsumers();

        if (empty($this->activeConsumers)) {
            $this->warn('No consumers found or matching the given filter.');
            return self::FAILURE;
        }

        $connection = null;
        $channel = null;

        try {
            $this->info('Connecting to RabbitMQ server...');

            $connection = RabbitMqConnectionFactory::create();
            $channel = $connection->channel();

            $this->registerConsumers($channel);

            $this->newLine();
            $this->info('RabbitMQ consumer is RUNNING.');
            $this->info('Listening to registered queues...');
            $this->info('Press CTRL+C to exit.');

            // consume() is the library's wait-loop: it checks heartbeats internally
            $channel->consume();

            return self::SUCCESS;
        } catch (AMQPBasicCancelException $exception) {
            Log::error('RabbitMQ consumer canceled by server', [
                'message' => $exception->getMessage(),
            ]);

            $this->error('Consumer canceled by server.');
            return self::FAILURE;
        } catch (AMQPConnectionClosedException $exception) {
            Log::error('RabbitMQ connection closed unexpectedly', [
                'message' => $exception->getMessage(),
            ]);

            $this->error('Connection closed unexpectedly.');
            return self::FAILURE;
        } catch (Throwable $exception) {
            Log::error('RabbitMqConsumeCommand@handle', [
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            $this->error('RabbitMQ Consumer failed: ' . $exception->getMessage());
            return self::FAILURE;
        } finally {
            $channel?->close();
            $connection?->close();

            $this->info('RabbitMQ connection closed.');
        }
    }

    /**
     * Resolve the active list of consumers from property, trait, or config.
     */
    protected function resolveConsumers(): void
    {
        // 1. Check if consumers array was overridden on the class instance
        $configured = $this->consumers ?? config('rabbitmq_broadcast.consumers') ?? [];

        $fromFilter = $this->option('from') ?? $this->option('route');

        if ($fromFilter) {
            $configured = array_values(array_filter(
                $configured,
                fn (array $c) => ($c['from'] ?? $c['route'] ?? null) === $fromFilter
            ));
        }

        $this->activeConsumers = $configured;
    }

    /**
     * Declare exchanges, queues, bindings, and start consuming.
     */
    protected function registerConsumers(AMQPChannel $channel): void
    {
        $prefetchCount = (int) config('rabbitmq_broadcast.consumer.prefetch_count', 1);

        foreach ($this->activeConsumers as $consumer) {
            $from = $consumer['from'] ?? $consumer['route'] ?? 'default';
            $exchange = $consumer['exchange'];
            $queue = $consumer['queue'];

            $channel->exchange_declare(
                $exchange,
                'fanout',
                false,
                true,
                false
            );

            $channel->queue_declare(
                $queue,
                false,
                true,
                false,
                false
            );

            $channel->queue_bind(
                $queue,
                $exchange
            );

            // global=false applies prefetch count per consumer independently
            $channel->basic_qos(
                0,
                $prefetchCount,
                false
            );

            $this->info("Registered [{$from}] → exchange [{$exchange}] → queue [{$queue}]");

            $channel->basic_consume(
                $queue,
                '',
                false,
                false,
                false,
                false,
                function (AMQPMessage $message) use ($channel, $from): void {
                    $this->handleMessage(
                        $message,
                        $channel,
                        $from
                    );
                }
            );
        }
    }

    /**
     * Handle incoming AMQP message.
     */
    protected function handleMessage(
        AMQPMessage $message,
        AMQPChannel $channel,
        string $from
    ): void {
        try {
            $data = json_decode(
                $message->getBody(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            $sender = $data['from'] ?? $from;
            $item = $data['item'] ?? $data['model'] ?? 'Unknown';

            $this->comment(sprintf('[%s] Received item [%s] from: %s', $from, $item, $sender));

            $this->dispatchToHandler($from, $data);

            $channel->basic_ack(
                $message->getDeliveryTag()
            );

            $this->info("[{$from}] Message processed successfully.");
        } catch (Throwable $exception) {
            Log::error('Error processing RabbitMQ payload', [
                'from' => $from,
                'message' => $exception->getMessage(),
                'payload' => $message->getBody(),
                'exception' => $exception,
            ]);

            $this->error("[{$from}] Failed: {$exception->getMessage()}");

            $requeue = $this->option('requeue')
                || (bool) config('rabbitmq_broadcast.consumer.requeue_on_failure', false);

            $channel->basic_nack(
                $message->getDeliveryTag(),
                false,
                $requeue
            );
        }
    }

    /**
     * Resolve and invoke the handler registered for this from/item pair.
     *
     * @param array<string, mixed> $data
     */
    protected function dispatchToHandler(string $from, array $data): void
    {
        $item = $data['item'] ?? $data['model'] ?? null;
        $consumer = $this->findConsumer($from);
        $handler = $consumer['items'][$item] ?? $consumer['model'][$item] ?? $consumer['item'][$item] ?? null;

        if ($handler === null) {
            $this->warn("[{$from}] Unhandled item: " . ($item ?? 'Unknown'));
            return;
        }

        // Support [ServiceClass::class, 'methodName']
        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            app($class)->{$method}($data['data'] ?? [], $data);
            return;
        }

        // Support Invokable class
        if (is_string($handler) && class_exists($handler)) {
            $instance = app($handler);
            if (is_callable($instance)) {
                $instance($data['data'] ?? [], $data);
                return;
            }
        }

        // Support Closures/Callables
        if (is_callable($handler)) {
            $handler($data['data'] ?? [], $data);
            return;
        }

        throw new RuntimeException("Invalid handler format for item [{$item}] from [{$from}]");
    }

    /**
     * Find consumer definition by sender/route name.
     *
     * @return array{from?: string, route?: string, exchange: string, queue: string, items?: array<string, mixed>, model?: array<string, mixed>, item?: array<string, mixed>}
     */
    protected function findConsumer(string $from): array
    {
        foreach ($this->activeConsumers as $consumer) {
            if (($consumer['from'] ?? $consumer['route'] ?? null) === $from) {
                return $consumer;
            }
        }

        throw new RuntimeException("Unknown consumer for [{$from}]");
    }
}
