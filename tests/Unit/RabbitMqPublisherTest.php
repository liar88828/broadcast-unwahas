<?php

declare(strict_types=1);

namespace Unwahas\Broadcast\Tests\Unit;

use Mockery;
use Unwahas\Broadcast\Services\RabbitMqPublisher;
use Unwahas\Broadcast\Tests\TestCase;

class RabbitMqPublisherTest extends TestCase
{
    public function test_send_dosen_formats_payload_and_calls_exchange(): void
    {
        /** @var RabbitMqPublisher|Mockery\MockInterface $publisher */
        $publisher = Mockery::mock(RabbitMqPublisher::class)->makePartial()->shouldAllowMockingProtectedMethods();

        $publisher->shouldReceive('exchange')
            ->once()
            ->withArgs(function ($data, $from, $item, $exchange) {
                return $data['id'] === 123
                    && $data['email'] === 'dosen@unwahas.ac.id'
                    && $data['nama'] === 'Dr. Budi'
                    && $data['gelar_depan'] === 'Dr.'
                    && $data['gelar_belakang'] === 'M.Kom'
                    && isset($data['sent_at'])
                    && $from === 'sikawan'
                    && $item === 'Biodata'
                    && $exchange === 'laravel_exchange_sikawan';
            })
            ->andReturn(true);

        $result = $publisher->sendDosen([
            'id' => 123,
            'email' => 'dosen@unwahas.ac.id',
            'nama' => 'Dr. Budi',
            'gelar_depan' => 'Dr.',
            'gelar_belakang' => 'M.Kom',
        ], from: 'sikawan', exchange: 'laravel_exchange_sikawan');

        $this->assertTrue($result);
    }

    public function test_publish_delegates_to_exchange(): void
    {
        /** @var RabbitMqPublisher|Mockery\MockInterface $publisher */
        $publisher = Mockery::mock(RabbitMqPublisher::class)->makePartial()->shouldAllowMockingProtectedMethods();

        $publisher->shouldReceive('exchange')
            ->once()
            ->with(['key' => 'val'], 'sikawan', 'TestItem', 'test_exchange')
            ->andReturn(true);

        $result = $publisher->publish('test_exchange', 'TestItem', ['key' => 'val'], 'sikawan');

        $this->assertTrue($result);
    }

    public function test_exchange_handles_exceptions_gracefully(): void
    {
        // When RabbitMQ broker is unreachable, exchange() should report exception and return false
        $publisher = new RabbitMqPublisher();

        $result = $publisher->exchange(
            data: ['test' => true],
            from: 'sikawan',
            item: 'Test',
            exchange: 'non_existent_exchange'
        );

        $this->assertFalse($result);
    }

    public function test_queue_handles_exceptions_gracefully(): void
    {
        // When RabbitMQ broker is unreachable, queue() should report exception and return false
        $publisher = new RabbitMqPublisher();

        $result = $publisher->queue(
            data: ['test' => true],
            from: 'sikawan',
            item: 'Test',
            queue: 'non_existent_queue'
        );

        $this->assertFalse($result);
    }
}

