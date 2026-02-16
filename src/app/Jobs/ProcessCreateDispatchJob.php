<?php

declare(strict_types=1);

namespace App\Jobs;

class ProcessCreateDispatchJob extends BaseProcessJob
{
    public function __construct(
        string $idempotencyKey,
        string $source,
        string $type,
        string $scopeKey,
        array $payload,
        public string $occurrenceId,
        public string $resourceCode,
        ?string $commandId = null,
    ) {
        parent::__construct($idempotencyKey, $source, $type, $scopeKey, $payload, $commandId);
    }
}

