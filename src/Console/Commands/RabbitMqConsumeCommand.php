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
                            {--route= : Specific route name to consume (default: all configured routes)}
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
     *     route: string,
     *     exchange: string,
     *     queue: string,
     *     item: array<string, array{0: string, 1: string}|string|callable>
     * }>
     */
    protected array $activeConsumers = [];

    public function handle(): int
    {
        $this->resolveConsumers();

        if (empty($this->activeConsumers)) {
            $this->warn('No consumers found or matching the given route filter.');
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

        $routeFilter = $this->option('route');

        if ($routeFilter) {
            $configured = array_values(array_filter(
                $configured,
                fn (array $c) => ($c['route'] ?? null) === $routeFilter
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
            $route = $consumer['route'] ?? 'default';
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

            $this->info("Registered [{$route}] → exchange [{$exchange}] → queue [{$queue}]");

            $channel->basic_consume(
                $queue,
                '',
                false,
                false,
                false,
                false,
                function (AMQPMessage $message) use ($channel, $route): void {
                    $this->handleMessage(
                        $message,
                        $channel,
                        $route
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
        string $route
    ): void {
        try {
            $data = json_decode(
                $message->getBody(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            $from = $data['from'] ?? 'Unknown';
            $model = $data['model'] ?? 'Unknown';

            $this->comment(sprintf('[%s] Received model [%s] from: %s', $route, $model, $from));

            $this->dispatchToHandler($route, $data);

            $channel->basic_ack(
                $message->getDeliveryTag()
            );

            $this->info("[{$route}] Message processed successfully.");
        } catch (Throwable $exception) {
            Log::error('Error processing RabbitMQ payload', [
                'route' => $route,
                'message' => $exception->getMessage(),
                'payload' => $message->getBody(),
                'exception' => $exception,
            ]);

            $this->error("[{$route}] Failed: {$exception->getMessage()}");

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
     * Resolve and invoke the handler registered for this route/model pair.
     *
     * @param array<string, mixed> $data
     */
    protected function dispatchToHandler(string $route, array $data): void
    {
        $model = $data['model'] ?? null;
        $consumer = $this->findConsumer($route);
        $handler = $consumer['item'][$model] ?? null;

        if ($handler === null) {
            $this->warn("[{$route}] Unhandled model: " . ($model ?? 'Unknown'));
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

        throw new RuntimeException("Invalid handler format for model [{$model}] on route [{$route}]");
    }

    /**
     * Find consumer definition by route name.
     *
     * @return array{route: string, exchange: string, queue: string, item: array<string, mixed>}
     */
    protected function findConsumer(string $route): array
    {
        foreach ($this->activeConsumers as $consumer) {
            if (($consumer['route'] ?? null) === $route) {
                return $consumer;
            }
        }

        throw new RuntimeException("Unknown consumer route [{$route}]");
    }
}
