<?php

declare(strict_types=1);

use Bschmitt\Amqp\Facades\Amqp;
use JeromeHipolito\AmqpToolkit\Enums\FailedAmqpMessageStatus;
use JeromeHipolito\AmqpToolkit\Models\FailedAmqpMessage;

it('can list failed amqp messages', function () {
    FailedAmqpMessage::factory()->count(3)->create();

    $this->artisan('amqp:failed')
        ->expectsOutput('Total: 3 failed messages')
        ->assertSuccessful();
});

it('can list failed amqp messages with queue filter', function () {
    FailedAmqpMessage::factory()->create(['queue_name' => 'queue-1']);
    FailedAmqpMessage::factory()->create(['queue_name' => 'queue-2']);

    $this->artisan('amqp:failed', ['--queue' => 'queue-1'])
        ->expectsOutput('Total: 1 failed messages')
        ->assertSuccessful();
});

it('can retry failed amqp messages', function () {
    Amqp::shouldReceive('publish')
        ->times(2)
        ->andReturn(true);

    FailedAmqpMessage::factory()->count(2)->create([
        'status'        => FailedAmqpMessageStatus::FAILED,
        'next_retry_at' => now()->subMinute(),
        'retry_count'   => 0,
        'max_retries'   => 3,
    ]);

    $this->artisan('amqp:retry-failed')
        ->expectsOutput('Successfully processed 2 failed messages for retry.')
        ->assertSuccessful();
});

it('can force retry specific message', function () {
    Amqp::shouldReceive('publish')
        ->once()
        ->andReturn(true);

    $message = FailedAmqpMessage::factory()->create([
        'retry_count' => 0,
        'max_retries' => 3,
    ]);

    $this->artisan('amqp:force-retry', ['id' => $message->id])
        ->expectsOutput('Message successfully queued for retry.')
        ->assertSuccessful();

    $message->refresh();
    expect($message->retry_count)->toBe(1);
});

it('can force retry message with reset count', function () {
    Amqp::shouldReceive('publish')
        ->once()
        ->andReturn(true);

    $message = FailedAmqpMessage::factory()->deadLetter()->create();

    $this->artisan('amqp:force-retry', [
        'id'            => $message->id,
        '--reset-count' => true,
    ])
        ->expectsOutput('Reset retry count to 0.')
        ->expectsOutput('Message successfully queued for retry.')
        ->assertSuccessful();

    $message->refresh();
    expect($message->retry_count)->toBe(1);
    expect($message->status)->toBe(FailedAmqpMessageStatus::RETRYING);
});

it('fails to retry message that exceeded max retries without reset', function () {
    $message = FailedAmqpMessage::factory()->deadLetter()->create();

    $this->artisan('amqp:force-retry', ['id' => $message->id])
        ->expectsOutput('Message has exceeded maximum retries. Use --reset-count to force retry.')
        ->assertFailed();
});

it('can purge dead letter messages', function () {
    FailedAmqpMessage::factory()->count(2)->deadLetter()->create();
    FailedAmqpMessage::factory()->create(['status' => FailedAmqpMessageStatus::FAILED]);

    $this->artisan('amqp:purge-dead-letters', ['--force' => true])
        ->expectsOutput('Purged 2 dead letter messages.')
        ->assertSuccessful();

    expect(FailedAmqpMessage::where('status', FailedAmqpMessageStatus::DEAD_LETTER)->count())->toBe(0);
    expect(FailedAmqpMessage::where('status', FailedAmqpMessageStatus::FAILED)->count())->toBe(1);
});

it('shows no messages when none exist', function () {
    $this->artisan('amqp:failed')
        ->expectsOutput('No failed AMQP messages found.')
        ->assertSuccessful();
});

it('shows no retry messages when none ready', function () {
    FailedAmqpMessage::factory()->create([
        'next_retry_at' => now()->addHour(),
    ]);

    $this->artisan('amqp:retry-failed')
        ->expectsOutput('No failed messages ready for retry.')
        ->assertSuccessful();
});
