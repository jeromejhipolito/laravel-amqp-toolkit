<?php

declare(strict_types=1);

namespace JeromeJHipolito\AmqpToolkit;

use Illuminate\Support\ServiceProvider;
use JeromeJHipolito\AmqpToolkit\Console\Commands\ForceRetryAmqpMessage;
use JeromeJHipolito\AmqpToolkit\Console\Commands\ListFailedAmqpMessages;
use JeromeJHipolito\AmqpToolkit\Console\Commands\PurgeDeadLetterMessages;
use JeromeJHipolito\AmqpToolkit\Console\Commands\RetryFailedAmqpMessages;
use JeromeJHipolito\AmqpToolkit\Services\AmqpRetryService;

class AmqpToolkitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AmqpRetryService::class);
    }

    public function boot(): void
    {
        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'amqp-toolkit-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ListFailedAmqpMessages::class,
                RetryFailedAmqpMessages::class,
                ForceRetryAmqpMessage::class,
                PurgeDeadLetterMessages::class,
            ]);
        }
    }
}
