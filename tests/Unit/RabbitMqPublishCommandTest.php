<?php

declare(strict_types=1);

namespace Unwahas\Broadcast\Tests\Unit;

use Unwahas\Broadcast\Facades\RabbitMqBroadcast;
use Unwahas\Broadcast\Tests\TestCase;

class RabbitMqPublishCommandTest extends TestCase
{
    public function test_publish_command_fails_when_exchange_or_item_missing(): void
    {
        $this->artisan('rabbitmq:publish', [
            '--exchange' => '',
            '--item' => '',
        ])->assertFailed();
    }

    public function test_publish_command_fails_when_json_is_invalid(): void
    {
        $this->artisan('rabbitmq:publish', [
            '--exchange' => 'test_exchange',
            '--item' => 'Biodata',
            '--payload' => 'invalid-json-string',
        ])->assertFailed();
    }

    public function test_publish_command_succeeds_with_item_option(): void
    {
        RabbitMqBroadcast::shouldReceive('publish')
            ->once()
            ->with('laravel_exchange_sikawan', 'Biodata', ['id' => 123, 'nama' => 'Budi'], 'sikawan')
            ->andReturn(true);

        $this->artisan('rabbitmq:publish', [
            '--exchange' => 'laravel_exchange_sikawan',
            '--item' => 'Biodata',
            '--payload' => json_encode(['id' => 123, 'nama' => 'Budi']),
            '--from' => 'sikawan',
        ])
            ->expectsOutput('Publishing to exchange [laravel_exchange_sikawan] for item [Biodata]...')
            ->expectsOutput('Message published successfully!')
            ->assertSuccessful();
    }

    public function test_publish_command_succeeds_with_model_alias_option(): void
    {
        RabbitMqBroadcast::shouldReceive('publish')
            ->once()
            ->with('laravel_exchange_sikawan', 'Biodata', ['id' => 456], 'sikawan')
            ->andReturn(true);

        $this->artisan('rabbitmq:publish', [
            '--exchange' => 'laravel_exchange_sikawan',
            '--model' => 'Biodata',
            '--payload' => json_encode(['id' => 456]),
            '--from' => 'sikawan',
        ])
            ->expectsOutput('Publishing to exchange [laravel_exchange_sikawan] for item [Biodata]...')
            ->expectsOutput('Message published successfully!')
            ->assertSuccessful();
    }
}

