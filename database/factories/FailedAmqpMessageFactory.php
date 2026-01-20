<?php

declare(strict_types=1);

namespace JeromeHipolito\AmqpToolkit\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use JeromeHipolito\AmqpToolkit\Enums\FailedAmqpMessageStatus;
use JeromeHipolito\AmqpToolkit\Models\FailedAmqpMessage;

class FailedAmqpMessageFactory extends Factory
{
    protected $model = FailedAmqpMessage::class;

    public function definition(): array
    {
        return [
            'uuid'          => (string) Str::uuid(),
            'queue_name'    => 'test-queue',
            'routing_key'   => 'test.routing',
            'exchange'      => 'test-exchange',
            'payload'       => json_encode(['test' => 'data']),
            'exception'     => 'Exception: Test error',
            'retry_count'   => 0,
            'max_retries'   => 3,
            'failed_at'     => now(),
            'next_retry_at' => now()->addMinute(),
            'status'        => FailedAmqpMessageStatus::FAILED,
        ];
    }

    public function deadLetter(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'        => FailedAmqpMessageStatus::DEAD_LETTER,
            'retry_count'   => $attributes['max_retries'],
            'next_retry_at' => null,
        ]);
    }

    public function retrying(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'        => FailedAmqpMessageStatus::RETRYING,
            'retry_count'   => 1,
            'last_retry_at' => now(),
        ]);
    }
}
