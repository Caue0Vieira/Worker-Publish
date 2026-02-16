<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Repositories;

use Domain\Outbox\Repositories\OutboxWriteRepositoryInterface;
use Domain\Shared\ValueObjects\Uuid;
use Illuminate\Support\Facades\DB;

class OutboxWriteRepository implements OutboxWriteRepositoryInterface
{
    public function addPendingEvent(
        string $aggregateType,
        string $aggregateId,
        string $eventType,
    ): void {
        DB::table('outbox')->insertOrIgnore([
            'id' => Uuid::generate()->toString(),
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'event_type' => $eventType,
            'status' => 'PENDING',
            'created_at' => now(),
            'sent_at' => null,
        ]);
    }

    public function markAsSent(string $outboxId): void
    {
        DB::table('outbox')
            ->where('id', $outboxId)
            ->update([
                'status' => 'SENT',
                'sent_at' => now(),
            ]);
    }

    public function markAsFailed(string $outboxId, ?string $errorMessage = null): void
    {
        DB::table('outbox')
            ->where('id', $outboxId)
            ->update([
                'status' => 'FAILED',
            ]);
    }

    public function markAsProcessing(string $outboxId): bool
    {
        $updatedRows = DB::table('outbox')
            ->where('id', $outboxId)
            ->where('status', 'PENDING')
            ->update([
                'status' => 'PROCESSING',
            ]);

        return $updatedRows > 0;
    }
}

