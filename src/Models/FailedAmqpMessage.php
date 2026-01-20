<?php

declare(strict_types=1);

namespace JeromeJHipolito\AmqpToolkit\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use JeromeJHipolito\AmqpToolkit\Enums\FailedAmqpMessageStatus;
use JeromeJHipolito\AmqpToolkit\Traits\HasUuid;

class FailedAmqpMessage extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'uuid',
        'queue_name',
        'routing_key',
        'exchange',
        'payload',
        'exception',
        'retry_count',
        'max_retries',
        'failed_at',
        'last_retry_at',
        'next_retry_at',
        'status',
    ];

    protected $casts = [
        'failed_at'     => 'datetime',
        'last_retry_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'retry_count'   => 'integer',
        'max_retries'   => 'integer',
        'status'        => FailedAmqpMessageStatus::class,
    ];

    public function canRetry(): bool
    {
        return $this->retry_count < $this->max_retries
            && $this->status !== FailedAmqpMessageStatus::DEAD_LETTER;
    }

    public function isReadyForRetry(): bool
    {
        return $this->canRetry() && $this->next_retry_at && $this->next_retry_at->isPast();
    }

    public function markAsDeadLetter(): void
    {
        $this->update([
            'status'        => FailedAmqpMessageStatus::DEAD_LETTER,
            'next_retry_at' => null,
        ]);
    }

    public function incrementRetry(): void
    {
        $this->increment('retry_count');
        $this->update([
            'last_retry_at' => now(),
            'next_retry_at' => $this->calculateNextRetryAt(),
            'status'        => $this->canRetry()
                ? FailedAmqpMessageStatus::RETRYING
                : FailedAmqpMessageStatus::DEAD_LETTER,
        ]);
    }

    public function resetRetryCount(): void
    {
        $this->update([
            'retry_count'   => 0,
            'status'        => FailedAmqpMessageStatus::FAILED,
            'next_retry_at' => now(),
        ]);
    }

    private function calculateNextRetryAt(): ?Carbon
    {
        if (! $this->canRetry()) {
            return null;
        }

        $backoffMinutes = match ($this->retry_count) {
            0       => 1,
            1       => 5,
            2       => 15,
            default => 60,
        };

        return now()->addMinutes($backoffMinutes);
    }

    protected static function newFactory()
    {
        return \JeromeJHipolito\AmqpToolkit\Database\Factories\FailedAmqpMessageFactory::new();
    }
}
