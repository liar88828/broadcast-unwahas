<?php

declare(strict_types=1);

namespace Unwahas\Broadcast;

use Illuminate\Support\ServiceProvider;
use Unwahas\Broadcast\Console\Commands\RabbitMqConsumeCommand;
use Unwahas\Broadcast\Console\Commands\RabbitMqPublishCommand;
use Unwahas\Broadcast\Services\RabbitMQService;
use Unwahas\Broadcast\Services\RabbitMqPublisher;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/rabbitmq_broadcast.php',
            'rabbitmq_broadcast'
        );

        $this->app->singleton(RabbitMQService::class, function () {
            return new RabbitMQService();
        });

        $this->app->alias(RabbitMQService::class, RabbitMqPublisher::class);
        $this->app->alias(RabbitMQService::class, 'rabbitmq.broadcast');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/rabbitmq_broadcast.php' => config_path('rabbitmq_broadcast.php'),
            ], 'rabbitmq-broadcast-config');

            $this->commands([
                RabbitMqConsumeCommand::class,
                RabbitMqPublishCommand::class,
            ]);
        }
    }
}
