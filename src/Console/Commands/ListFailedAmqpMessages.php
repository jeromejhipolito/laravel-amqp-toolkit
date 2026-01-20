<?php

declare(strict_types=1);

namespace JeromeHipolito\AmqpToolkit\Console\Commands;

use Illuminate\Console\Command;
use JeromeHipolito\AmqpToolkit\Services\AmqpRetryService;

class ListFailedAmqpMessages extends Command
{
    protected $signature = 'amqp:failed 
                           {--queue= : Filter by specific queue}
                           {--status= : Filter by status (failed, retrying, dead_letter)}';

    protected $description = 'List failed AMQP messages';

    public function handle(AmqpRetryService $retryService): int
    {
        $filters = array_filter([
            'queue_name' => $this->option('queue'),
            'status'     => $this->option('status'),
        ]);

        $messages = $retryService->getFailedMessages($filters);

        if ($messages->isEmpty()) {
            $this->info('No failed AMQP messages found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'UUID', 'Queue', 'Status', 'Retries', 'Failed At', 'Next Retry'],
            $messages->map(function ($message) {
                return [
                    $message->id,
                    substr($message->uuid, 0, 8).'...',
                    $message->queue_name,
                    $message->status->value,
                    "{$message->retry_count}/{$message->max_retries}",
                    $message->failed_at->format('Y-m-d H:i:s'),
                    $message->next_retry_at?->format('Y-m-d H:i:s') ?? 'N/A',
                ];
            })->toArray()
        );

        $this->info("Total: {$messages->count()} failed messages");

        return self::SUCCESS;
    }
}
