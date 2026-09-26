<?php

declare(strict_types=1);

namespace Unwahas\Broadcast\Console\Commands;

use Illuminate\Console\Command;
use Unwahas\Broadcast\Facades\RabbitMqBroadcast;

class RabbitMqPublishCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbitmq:publish
                            {--exchange= : The RabbitMQ exchange name}
                            {--item= : The Item or Model name (e.g. Biodata, Mahasiswa)}
                            {--model= : (Alias for --item) The item name}
                            {--payload= : JSON payload data to send}
                            {--from= : Sender identifier (default: config app_name)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish / Broadcast a message to a RabbitMQ exchange';

    public function handle(): int
    {
        $exchange = (string) ($this->option('exchange') ?? '');
        $item = (string) ($this->option('item') ?? $this->option('model') ?? '');
        $payloadRaw = (string) ($this->option('payload') ?? '{}');
        $from = $this->option('from');

        if (empty($exchange) || empty($item)) {
            $this->error('Exchange and item are required.');
            return self::FAILURE;
        }

        $data = json_decode((string) $payloadRaw, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Invalid JSON payload provided: ' . json_last_error_msg());
            return self::FAILURE;
        }

        $this->info("Publishing to exchange [{$exchange}] for item [{$item}]...");

        $success = RabbitMqBroadcast::publish($exchange, $item, $data, $from);

        if ($success) {
            $this->info('Message published successfully!');
            return self::SUCCESS;
        }

        $this->error('Failed to publish message.');
        return self::FAILURE;
    }
}
