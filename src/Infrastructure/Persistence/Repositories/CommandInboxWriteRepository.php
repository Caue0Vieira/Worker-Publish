<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Repositories;

use Domain\Idempotency\Repositories\CommandInboxWriteRepositoryInterface;
use Illuminate\Support\Facades\DB;

class CommandInboxWriteRepository implements CommandInboxWriteRepositoryInterface
{
    public function markAsEnqueued(string $commandId): bool
    {
        $updatedRows = DB::table('command_inbox')
            ->where('id', $commandId)
            ->where('status', 'RECEIVED')
            ->update([
                'status' => 'ENQUEUED',
                'updated_at' => now(),
            ]);

        return $updatedRows > 0;
    }
}
