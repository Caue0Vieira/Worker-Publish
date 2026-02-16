<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Repositories;

use Domain\Idempotency\Entities\CommandInBox;
use Domain\Idempotency\Repositories\CommandInboxReadRepositoryInterface;
use Illuminate\Support\Facades\DB;

class CommandInboxReadRepository implements CommandInboxReadRepositoryInterface
{
    public function findByCommandId(string $commandId): ?CommandInBox
    {
        $row = DB::table('command_inbox')
            ->where('id', $commandId)
            ->first();

        if ($row === null) {
            return null;
        }

        return CommandInBox::fromArray([
            'id' => $row->id,
            'idempotency_key' => $row->idempotency_key,
            'source' => $row->source,
            'type' => $row->type,
            'scope_key' => $row->scope_key,
            'payload_hash' => $row->payload_hash,
            'payload' => $row->payload,
            'status' => $row->status,
            'result' => $row->result,
            'error_message' => $row->error_message,
            'processed_at' => $row->processed_at,
            'expires_at' => $row->expires_at,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ]);
    }
}

