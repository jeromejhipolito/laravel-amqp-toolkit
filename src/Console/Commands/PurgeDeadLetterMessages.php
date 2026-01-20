<?php

declare(strict_types=1);

namespace JeromeHipolito\AmqpToolkit\Console\Commands;

use Illuminate\Console\Command;
use JeromeHipolito\AmqpToolkit\Services\AmqpRetryService;

class PurgeDeadLetterMessages extends Command
{
    protected $signature = 'amqp:purge-dead-letters 
                           {--force : Skip confirmation prompt}';

    protected $description = 'Purge all dead letter AMQP messages';

    public function handle(AmqpRetryService $retryService): int
    {
        if (! $this->option('force')) {
            if (! $this->confirm('This will permanently delete all dead letter messages. Continue?')) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        $deleted = $retryService->purgeDeadLetters();

        $this->info("Purged {$deleted} dead letter messages.");

        return self::SUCCESS;
    }
}
