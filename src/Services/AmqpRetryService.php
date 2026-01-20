<?php

declare(strict_types=1);

namespace JeromeJHipolito\AmqpToolkit\Services;

use Bschmitt\Amqp\Facades\Amqp;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JeromeJHipolito\AmqpToolkit\Enums\FailedAmqpMessageStatus;
use JeromeJHipolito\AmqpToolkit\Models\FailedAmqpMessage;

class AmqpRetryService
{
    public function handleFailedMessage(
        string $queueName,
        string $routingKey,
        string $exchange,
        string $payload,
        \Exception $exception,
        int $maxRetries = 3
    ): FailedAmqpMessage {
        return FailedAmqpMessage::create([
            'uuid'          => (string) Str::uuid(),
            'queue_name'    => $queueName,
            'routing_key'   => $routingKey,
            'exchange'      => $exchange,
            'payload'       => $payload,
            'exception'     => $this->formatException($exception),
            'retry_count'   => 0,
            'max_retries'   => $maxRetries,
            'failed_at'     => now(),
            'next_retry_at' => now()->addMinute(),
            'status'        => FailedAmqpMessageStatus::FAILED,
        ]);
    }

    public function retryMessage(FailedAmqpMessage $failedMessage): bool
    {
        if (! $failedMessage->canRetry()) {
            return false;
        }

        try {
            Amqp::publish(
                $failedMessage->routing_key,
                $failedMessage->payload,
                [
                    'exchange'      => $failedMessage->exchange,
                    'exchange_type' => 'topic',
                    'persistent'    => true,
                ]
            );

            $failedMessage->incrementRetry();

            return true;
        } catch (\Exception $e) {
            report($e);

            $failedMessage->incrementRetry();

            return false;
        }
    }

    public function processRetryQueue(int $limit = 100): int
    {
        $processed = 0;

        $readyMessages = FailedAmqpMessage::where('status', FailedAmqpMessageStatus::FAILED)
            ->where('next_retry_at', '<=', now())
            ->where('retry_count', '<', DB::raw('max_retries'))
            ->limit($limit)
            ->get();

        foreach ($readyMessages as $message) {
            if ($this->retryMessage($message)) {
                $processed++;
            }
        }

        return $processed;
    }

    public function getFailedMessages(array $filters = []): Collection
    {
        $query = FailedAmqpMessage::query();

        if (isset($filters['queue_name'])) {
            $query->where('queue_name', $filters['queue_name']);
        }

        if (isset($filters['status'])) {
            $status = $filters['status'];
            if (is_string($status)) {
                $status = FailedAmqpMessageStatus::tryFrom($status);
            }
            if ($status) {
                $query->where('status', $status);
            }
        }

        return $query->orderBy('failed_at', 'desc')->get();
    }

    public function purgeDeadLetters(): int
    {
        return FailedAmqpMessage::where('status', FailedAmqpMessageStatus::DEAD_LETTER)->delete();
    }

    public function getStatistics(): array
    {
        return [
            'total'       => FailedAmqpMessage::count(),
            'failed'      => FailedAmqpMessage::where('status', FailedAmqpMessageStatus::FAILED)->count(),
            'retrying'    => FailedAmqpMessage::where('status', FailedAmqpMessageStatus::RETRYING)->count(),
            'dead_letter' => FailedAmqpMessage::where('status', FailedAmqpMessageStatus::DEAD_LETTER)->count(),
        ];
    }

    private function formatException(\Exception $exception): string
    {
        return sprintf(
            "%s: %s\n\nStack trace:\n%s",
            get_class($exception),
            $exception->getMessage(),
            $exception->getTraceAsString()
        );
    }
}
