<?php

declare(strict_types=1);

namespace Infrastructure\Queue;

use App\Jobs\ProcessCloseDispatchJob;
use App\Jobs\ProcessCreateDispatchJob;
use App\Jobs\ProcessCreateOccurrenceJob;
use App\Jobs\ProcessResolveOccurrenceJob;
use App\Jobs\ProcessStartOccurrenceJob;
use App\Jobs\ProcessUpdateDispatchStatusJob;
use Domain\Idempotency\Entities\CommandInBox;
use Domain\Outbox\Entities\OutboxEvent;
use Domain\Outbox\Services\OutboxEventMapper;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class OutboxQueuePublisher
{
    private const COMMAND_TO_JOB_MAP = [
        'create_occurrence' => ProcessCreateOccurrenceJob::class,
        'start_occurrence' => ProcessStartOccurrenceJob::class,
        'resolve_occurrence' => ProcessResolveOccurrenceJob::class,
        'create_dispatch' => ProcessCreateDispatchJob::class,
        'close_dispatch' => ProcessCloseDispatchJob::class,
        'update_dispatch_status' => ProcessUpdateDispatchStatusJob::class,
    ];

    public function __construct(
        private OutboxEventMapper $eventMapper
    ) {
    }

    public function publishEvent(OutboxEvent $outboxEvent, CommandInBox $command): void
    {
        $commandType = $this->eventMapper->resolve($outboxEvent->eventType());
        $jobClass = $this->resolveJobClass($commandType);

        $payload = is_string($command->payload())
            ? json_decode($command->payload(), true)
            : (array) $command->payload();

        $job = $this->createJobInstance(
            $jobClass,
            $command->idempotencyKey(),
            $command->source(),
            $command->type(),
            $command->scopeKey(),
            $payload,
            $command->id()->toString(),
            $commandType
        );

        dispatch($job);

        Log::info('📤 [OutboxPublisher] Event published to queue', [
            'outboxId' => $outboxEvent->id()->toString(),
            'eventType' => $outboxEvent->eventType(),
            'commandType' => $commandType,
            'jobClass' => $jobClass,
            'commandId' => $command->id()->toString(),
        ]);
    }

    private function resolveJobClass(string $commandType): string
    {
        if (!isset(self::COMMAND_TO_JOB_MAP[$commandType])) {
            throw new InvalidArgumentException("Unsupported command type: {$commandType}");
        }

        return self::COMMAND_TO_JOB_MAP[$commandType];
    }

    private function createJobInstance(
        string $jobClass,
        string $idempotencyKey,
        string $source,
        string $type,
        string $scopeKey,
        array $payload,
        string $commandId,
        string $commandType
    ) {
        $jobParams = $this->extractJobParameters($commandType, $payload);

        $jobParams[] = $commandId;

        return new $jobClass(
            $idempotencyKey,
            $source,
            $type,
            $scopeKey,
            $payload,
            ...$jobParams
        );
    }

    private function extractJobParameters(string $commandType, array $payload): array
    {
        return match ($commandType) {
            'create_occurrence' => [
                $payload['externalId'] ?? '',
                $payload['type'] ?? '',
                $payload['description'] ?? '',
                $payload['reportedAt'] ?? now()->toIso8601String(),
            ],
            'start_occurrence' => [
                $payload['occurrenceId'] ?? '',
            ],
            'resolve_occurrence' => [
                $payload['occurrenceId'] ?? '',
            ],
            'create_dispatch' => [
                $payload['occurrenceId'] ?? '',
                $payload['resourceCode'] ?? '',
            ],
            'close_dispatch' => [
                $payload['dispatchId'] ?? '',
            ],
            'update_dispatch_status' => [
                $payload['dispatchId'] ?? '',
                $payload['statusCode'] ?? '',
            ],
            default => throw new InvalidArgumentException("Unsupported command type: {$commandType}"),
        };
    }
}

