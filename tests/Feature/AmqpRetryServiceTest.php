<?php

declare(strict_types=1);

use Bschmitt\Amqp\Facades\Amqp;
use JeromeHipolito\AmqpToolkit\Enums\FailedAmqpMessageStatus;
use JeromeHipolito\AmqpToolkit\Models\FailedAmqpMessage;
use JeromeHipolito\AmqpToolkit\Services\AmqpRetryService;

beforeEach(function () {
    $this->retryService = app(AmqpRetryService::class);
});

it('can handle failed message', function () {
    $exception = new Exception('Test error');

    $failedMessage = $this->retryService->handleFailedMessage(
        'test-queue',
        'test.routing',
        'test-exchange',
        '{"test": "data"}',
        $exception,
        3
    );

    expect($failedMessage)->toBeInstanceOf(FailedAmqpMessage::class);
    expect($failedMessage->queue_name)->toBe('test-queue');
    expect($failedMessage->routing_key)->toBe('test.routing');
    expect($failedMessage->exchange)->toBe('test-exchange');
    expect($failedMessage->payload)->toBe('{"test": "data"}');
    expect($failedMessage->retry_count)->toBe(0);
    expect($failedMessage->max_retries)->toBe(3);
    expect($failedMessage->status)->toBe(FailedAmqpMessageStatus::FAILED);

    $this->assertDatabaseHas('failed_amqp_messages', [
        'queue_name' => 'test-queue',
        'status'     => 'failed',
    ]);
});

it('can retry message successfully', function () {
    Amqp::shouldReceive('publish')
        ->once()
        ->andReturn(true);

    $failedMessage = FailedAmqpMessage::factory()->create([
        'retry_count' => 0,
        'max_retries' => 3,
        'status'      => FailedAmqpMessageStatus::FAILED,
    ]);

    $result = $this->retryService->retryMessage($failedMessage);

    expect($result)->toBeTrue();

    $failedMessage->refresh();
    expect($failedMessage->retry_count)->toBe(1);
    expect($failedMessage->status)->toBe(FailedAmqpMessageStatus::RETRYING);
    expect($failedMessage->last_retry_at)->not->toBeNull();
});

it('marks message as dead letter when max retries exceeded', function () {
    Amqp::shouldReceive('publish')
        ->once()
        ->andReturn(true);

    $failedMessage = FailedAmqpMessage::factory()->create([
        'retry_count' => 2,
        'max_retries' => 3,
        'status'      => FailedAmqpMessageStatus::FAILED,
    ]);

    $this->retryService->retryMessage($failedMessage);

    $failedMessage->refresh();
    expect($failedMessage->retry_count)->toBe(3);
    expect($failedMessage->status)->toBe(FailedAmqpMessageStatus::DEAD_LETTER);
    expect($failedMessage->next_retry_at)->toBeNull();
});

it('can process retry queue', function () {
    Amqp::shouldReceive('publish')
        ->times(3)
        ->andReturn(true);

    FailedAmqpMessage::factory()->count(3)->create([
        'status'        => FailedAmqpMessageStatus::FAILED,
        'retry_count'   => 0,
        'max_retries'   => 3,
        'next_retry_at' => now()->subMinute(),
    ]);

    FailedAmqpMessage::factory()->create([
        'status'        => FailedAmqpMessageStatus::FAILED,
        'retry_count'   => 0,
        'max_retries'   => 3,
        'next_retry_at' => now()->addMinute(),
    ]);

    $processed = $this->retryService->processRetryQueue();

    expect($processed)->toBe(3);
});

it('can get failed messages with filters', function () {
    FailedAmqpMessage::factory()->create([
        'queue_name' => 'queue-1',
        'status'     => FailedAmqpMessageStatus::FAILED,
    ]);

    FailedAmqpMessage::factory()->create([
        'queue_name' => 'queue-2',
        'status'     => FailedAmqpMessageStatus::DEAD_LETTER,
    ]);

    $allMessages = $this->retryService->getFailedMessages();
    expect($allMessages)->toHaveCount(2);

    $filteredByQueue = $this->retryService->getFailedMessages(['queue_name' => 'queue-1']);
    expect($filteredByQueue)->toHaveCount(1);
    expect($filteredByQueue->first()->queue_name)->toBe('queue-1');

    $filteredByStatus = $this->retryService->getFailedMessages(['status' => 'dead_letter']);
    expect($filteredByStatus)->toHaveCount(1);
    expect($filteredByStatus->first()->status)->toBe(FailedAmqpMessageStatus::DEAD_LETTER);
});

it('can purge dead letter messages', function () {
    FailedAmqpMessage::factory()->count(2)->create(['status' => FailedAmqpMessageStatus::DEAD_LETTER]);
    FailedAmqpMessage::factory()->create(['status' => FailedAmqpMessageStatus::FAILED]);

    $deleted = $this->retryService->purgeDeadLetters();

    expect($deleted)->toBe(2);
    expect(FailedAmqpMessage::where('status', FailedAmqpMessageStatus::DEAD_LETTER)->count())->toBe(0);
    expect(FailedAmqpMessage::where('status', FailedAmqpMessageStatus::FAILED)->count())->toBe(1);
});

it('can get statistics', function () {
    FailedAmqpMessage::factory()->count(2)->create(['status' => FailedAmqpMessageStatus::FAILED]);
    FailedAmqpMessage::factory()->count(3)->create(['status' => FailedAmqpMessageStatus::RETRYING]);
    FailedAmqpMessage::factory()->count(1)->create(['status' => FailedAmqpMessageStatus::DEAD_LETTER]);

    $stats = $this->retryService->getStatistics();

    expect($stats['total'])->toBe(6);
    expect($stats['by_status']['failed'])->toBe(2);
    expect($stats['by_status']['retrying'])->toBe(3);
    expect($stats['by_status']['dead_letter'])->toBe(1);
});
