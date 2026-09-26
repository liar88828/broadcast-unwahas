<?php

declare(strict_types=1);

namespace Unwahas\Broadcast\Tests\Unit;

use Mockery;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Unwahas\Broadcast\Console\Commands\RabbitMqConsumeCommand;
use Unwahas\Broadcast\Tests\TestCase;

class SampleConsumerService
{
    public static array $received = [];

    public function handleDosen(array $data, array $rawPayload): void
    {
        self::$received = [
            'data' => $data,
            'rawPayload' => $rawPayload,
        ];
    }
}

class SampleInvokableService
{
    public static array $received = [];

    public function __invoke(array $data, array $rawPayload): void
    {
        self::$received = [
            'data' => $data,
            'rawPayload' => $rawPayload,
        ];
    }
}

class TestableConsumeCommand extends RabbitMqConsumeCommand
{
    public function callResolveConsumers(): void
    {
        $this->resolveConsumers();
    }

    public function getActiveConsumers(): array
    {
        return $this->activeConsumers;
    }

    public function setActiveConsumers(array $consumers): void
    {
        $this->activeConsumers = $consumers;
    }

    public function callDispatchToHandler(string $from, array $data): void
    {
        $this->dispatchToHandler($from, $data);
    }

    public function callFindConsumer(string $from): array
    {
        return $this->findConsumer($from);
    }
}

class RabbitMqConsumeCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        SampleConsumerService::$received = [];
        SampleInvokableService::$received = [];
    }

    public function test_resolve_consumers_loads_all_from_config(): void
    {
        config()->set('rabbitmq_broadcast.consumers', [
            [
                'from' => 'sikawan',
                'exchange' => 'ex_sikawan',
                'queue' => 'q_sikawan',
                'items' => [
                    'Biodata' => [SampleConsumerService::class, 'handleDosen'],
                ],
            ],
            [
                'from' => 'simawa',
                'exchange' => 'ex_simawa',
                'queue' => 'q_simawa',
                'items' => [],
            ],
        ]);

        $command = new TestableConsumeCommand();
        $command->setLaravel($this->app);
        $input = new ArrayInput([], $command->getDefinition());
        $output = new BufferedOutput();
        $command->setInput($input);
        $command->setOutput($this->app->make(\Illuminate\Console\OutputStyle::class, ['input' => $input, 'output' => $output]));

        $command->callResolveConsumers();

        $active = $command->getActiveConsumers();
        $this->assertCount(2, $active);
        $this->assertSame('sikawan', $active[0]['from']);
        $this->assertSame('simawa', $active[1]['from']);
    }

    public function test_resolve_consumers_filters_by_from_option(): void
    {
        config()->set('rabbitmq_broadcast.consumers', [
            [
                'from' => 'sikawan',
                'exchange' => 'ex_sikawan',
                'queue' => 'q_sikawan',
                'items' => [],
            ],
            [
                'from' => 'simawa',
                'exchange' => 'ex_simawa',
                'queue' => 'q_simawa',
                'items' => [],
            ],
        ]);

        $command = new TestableConsumeCommand();
        $command->setLaravel($this->app);
        $input = new ArrayInput(['--from' => 'sikawan'], $command->getDefinition());
        $output = new BufferedOutput();
        $command->setInput($input);
        $command->setOutput($this->app->make(\Illuminate\Console\OutputStyle::class, ['input' => $input, 'output' => $output]));

        $command->callResolveConsumers();

        $active = $command->getActiveConsumers();
        $this->assertCount(1, $active);
        $this->assertSame('sikawan', $active[0]['from']);
    }

    public function test_resolve_consumers_filters_by_route_alias_option(): void
    {
        config()->set('rabbitmq_broadcast.consumers', [
            [
                'from' => 'sikawan',
                'exchange' => 'ex_sikawan',
                'queue' => 'q_sikawan',
                'items' => [],
            ],
            [
                'from' => 'simawa',
                'exchange' => 'ex_simawa',
                'queue' => 'q_simawa',
                'items' => [],
            ],
        ]);

        $command = new TestableConsumeCommand();
        $command->setLaravel($this->app);
        $input = new ArrayInput(['--route' => 'simawa'], $command->getDefinition());
        $output = new BufferedOutput();
        $command->setInput($input);
        $command->setOutput($this->app->make(\Illuminate\Console\OutputStyle::class, ['input' => $input, 'output' => $output]));

        $command->callResolveConsumers();

        $active = $command->getActiveConsumers();
        $this->assertCount(1, $active);
        $this->assertSame('simawa', $active[0]['from']);
    }

    public function test_dispatch_to_handler_invokes_service_class_method(): void
    {
        $command = new TestableConsumeCommand();
        $command->setLaravel($this->app);
        $input = new ArrayInput([], $command->getDefinition());
        $output = new BufferedOutput();
        $command->setInput($input);
        $command->setOutput($this->app->make(\Illuminate\Console\OutputStyle::class, ['input' => $input, 'output' => $output]));

        $command->setActiveConsumers([
            [
                'from' => 'sikawan',
                'exchange' => 'ex_sikawan',
                'queue' => 'q_sikawan',
                'items' => [
                    'Biodata' => [SampleConsumerService::class, 'handleDosen'],
                ],
            ],
        ]);

        $payload = [
            'from' => 'sikawan',
            'item' => 'Biodata',
            'data' => ['id' => 999, 'nama' => 'Testing Dosen'],
        ];

        $command->callDispatchToHandler('sikawan', $payload);

        $this->assertSame(['id' => 999, 'nama' => 'Testing Dosen'], SampleConsumerService::$received['data']);
        $this->assertSame($payload, SampleConsumerService::$received['rawPayload']);
    }

    public function test_dispatch_to_handler_invokes_invokable_class(): void
    {
        $command = new TestableConsumeCommand();
        $command->setLaravel($this->app);
        $input = new ArrayInput([], $command->getDefinition());
        $output = new BufferedOutput();
        $command->setInput($input);
        $command->setOutput($this->app->make(\Illuminate\Console\OutputStyle::class, ['input' => $input, 'output' => $output]));

        $command->setActiveConsumers([
            [
                'from' => 'sikawan',
                'exchange' => 'ex_sikawan',
                'queue' => 'q_sikawan',
                'items' => [
                    'Biodata' => SampleInvokableService::class,
                ],
            ],
        ]);

        $payload = [
            'from' => 'sikawan',
            'item' => 'Biodata',
            'data' => ['id' => 777],
        ];

        $command->callDispatchToHandler('sikawan', $payload);

        $this->assertSame(['id' => 777], SampleInvokableService::$received['data']);
    }

    public function test_dispatch_to_handler_invokes_closure(): void
    {
        $closureCalled = false;
        $receivedData = null;

        $command = new TestableConsumeCommand();
        $command->setLaravel($this->app);
        $input = new ArrayInput([], $command->getDefinition());
        $output = new BufferedOutput();
        $command->setInput($input);
        $command->setOutput($this->app->make(\Illuminate\Console\OutputStyle::class, ['input' => $input, 'output' => $output]));

        $command->setActiveConsumers([
            [
                'from' => 'sikawan',
                'exchange' => 'ex_sikawan',
                'queue' => 'q_sikawan',
                'items' => [
                    'Biodata' => function (array $data) use (&$closureCalled, &$receivedData) {
                        $closureCalled = true;
                        $receivedData = $data;
                    },
                ],
            ],
        ]);

        $payload = [
            'from' => 'sikawan',
            'item' => 'Biodata',
            'data' => ['id' => 555],
        ];

        $command->callDispatchToHandler('sikawan', $payload);

        $this->assertTrue($closureCalled);
        $this->assertSame(['id' => 555], $receivedData);
    }

    public function test_find_consumer_throws_exception_when_unknown(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown consumer for [non_existent]');

        $command = new TestableConsumeCommand();
        $command->setActiveConsumers([]);
        $command->callFindConsumer('non_existent');
    }
}

