<?php

declare(strict_types=1);

namespace JeromeHipolito\AmqpToolkit\Console\Commands;

use Illuminate\Console\Command;
use JeromeHipolito\AmqpToolkit\Enums\FailedAmqpMessageStatus;
use JeromeHipolito\AmqpToolkit\Models\FailedAmqpMessage;
use JeromeHipolito\AmqpToolkit\Services\AmqpRetryService;

class ForceRetryAmqpMessage extends Command
{
    protected $signature = 'amqp:force-retry 
                           {id : The ID of the failed message to retry}
                           {--reset-count : Reset retry count to 0}';

    protected $description = 'Force retry a specific failed AMQP message';

    public function handle(AmqpRetryService $retryService): int
    {
        $messageId  = $this->argument('id');
        $resetCount = $this->option('reset-count');

        $message = FailedAmqpMessage::find($messageId);

        if (! $message) {
            $this->error("Failed message with ID {$messageId} not found.");

            return self::FAILURE;
        }

        if ($resetCount) {
            $message->update([
                'retry_count'   => 0,
                'status'        => FailedAmqpMessageStatus::FAILED,
                'next_retry_at' => now(),
            ]);
            $this->info('Reset retry count to 0.');
        }

        if (! $message->canRetry() && ! $resetCount) {
            $this->error('Message has exceeded maximum retries. Use --reset-count to force retry.');

            return self::FAILURE;
        }

        $this->info("Attempting to retry message ID: {$messageId}");
        $this->info("Queue: {$message->queue_name}");
        $this->info("Routing Key: {$message->routing_key}");

        if ($retryService->retryMessage($message)) {
            $this->info('Message successfully queued for retry.');
        } else {
            $this->error('Failed to retry message. Check logs for details.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
