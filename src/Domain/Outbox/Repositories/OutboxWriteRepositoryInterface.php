<?php

declare(strict_types=1);

namespace Domain\Outbox\Repositories;

interface OutboxWriteRepositoryInterface
{
    public function addPendingEvent(
        string $aggregateType,
        string $aggregateId,
        string $eventType,
    ): void;

    public function markAsSent(string $outboxId): void;

    public function markAsFailed(string $outboxId, ?string $errorMessage = null): void;

    public function markAsProcessing(string $outboxId): bool;
}

