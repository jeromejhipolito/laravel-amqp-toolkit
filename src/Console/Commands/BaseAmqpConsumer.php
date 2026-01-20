<?php

declare(strict_types=1);

namespace JeromeHipolito\AmqpToolkit\Console\Commands;

use Bschmitt\Amqp\Facades\Amqp;
use Illuminate\Console\Command;
use JeromeHipolito\AmqpToolkit\Services\AmqpRetryService;

abstract class BaseAmqpConsumer extends Command
{
    protected AmqpRetryService $retryService;

    public function __construct()
    {
        parent::__construct();
        $this->retryService = app(AmqpRetryService::class);
    }

    abstract protected function getQueueName(): string;

    abstract protected function getRoutingKey(): string;

    abstract protected function getExchange(): string;

    abstract protected function validatePayload(array $data): bool;

    abstract protected function processMessage(array $data): void;

    public function getQueueNamePublic(): string
    {
        return $this->getQueueName();
    }

    public function getRoutingKeyPublic(): string
    {
        return $this->getRoutingKey();
    }

    public function getExchangePublic(): string
    {
        return $this->getExchange();
    }

    public function validatePayloadPublic(array $data): bool
    {
        return $this->validatePayload($data);
    }

    protected function getMaxRetries(): int
    {
        return 3;
    }

    protected function shouldAcknowledgeOnFailure(): bool
    {
        return false;
    }

    public function handle(): void
    {
        $queueName  = $this->getQueueName();
        $routingKey = $this->getRoutingKey();
        $exchange   = $this->getExchange();

        Amqp::consume($queueName, function ($message, $resolver) use ($queueName, $routingKey, $exchange) {
            $shouldAcknowledge = true;

            try {
                $data = json_decode($message->body, true);

                if (! is_array($data)) {
                    throw new \Exception('Invalid JSON payload.');
                }

                if (! $this->validatePayload($data)) {
                    throw new \Exception('Payload validation failed.');
                }

                $this->processMessage($data);

                $this->info("Message processed successfully for queue: {$queueName}");

            } catch (\Exception $e) {
                $this->error("Failed to process message: {$e->getMessage()}");

                $this->retryService->handleFailedMessage(
                    $queueName,
                    $routingKey,
                    $exchange,
                    $message->body,
                    $e,
                    $this->getMaxRetries()
                );

                report('AMQP message processing failed', [
                    'queue'       => $queueName,
                    'routing_key' => $routingKey,
                    'error'       => $e->getMessage(),
                    'body'        => $message->body,
                ]);

                $shouldAcknowledge = $this->shouldAcknowledgeOnFailure();
            }

            if ($shouldAcknowledge) {
                $resolver->acknowledge($message);
            } else {
                $resolver->reject($message, false);
            }
        }, [
            'exchange'      => $exchange,
            'exchange_type' => 'topic',
            'routing'       => $routingKey,
            'queue'         => $queueName,
            'persistent'    => true,
        ]);
    }
}
