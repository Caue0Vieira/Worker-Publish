<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Repositories;

use Domain\Outbox\Entities\OutboxEvent;
use Domain\Outbox\Repositories\OutboxReadRepositoryInterface;
use Illuminate\Support\Facades\DB;

class OutboxReadRepository implements OutboxReadRepositoryInterface
{
    public function findPendingEvents(int $limit): array
    {
        $events = DB::select("
            SELECT id, aggregate_type, aggregate_id, event_type, status, created_at
            FROM outbox
            WHERE status = 'PENDING'
            ORDER BY created_at ASC
            LIMIT ?
            FOR UPDATE SKIP LOCKED
        ", [$limit]);

        return array_map(function ($event) {
            return OutboxEvent::fromArray([
                'id' => $event->id,
                'aggregate_type' => $event->aggregate_type,
                'aggregate_id' => $event->aggregate_id,
                'event_type' => $event->event_type,
                'status' => $event->status,
                'created_at' => $event->created_at,
            ]);
        }, $events);
    }
}

