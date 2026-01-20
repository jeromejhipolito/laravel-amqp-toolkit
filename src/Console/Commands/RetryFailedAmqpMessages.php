<?php

declare(strict_types=1);

namespace JeromeHipolito\AmqpToolkit\Console\Commands;

use Illuminate\Console\Command;
use JeromeHipolito\AmqpToolkit\Services\AmqpRetryService;

class RetryFailedAmqpMessages extends Command
{
    protected $signature = 'amqp:retry-failed 
                           {--queue= : Specific queue to retry}
                           {--limit=100 : Maximum number of messages to retry}';

    protected $description = 'Retry failed AMQP messages that are ready for retry';

    public function handle(AmqpRetryService $retryService): int
    {
        $this->info('Processing failed AMQP messages for retry...');

        $processed = $retryService->processRetryQueue();

        if ($processed > 0) {
            $this->info("Successfully processed {$processed} failed messages for retry.");
        } else {
            $this->info('No failed messages ready for retry.');
        }

        return self::SUCCESS;
    }
}
