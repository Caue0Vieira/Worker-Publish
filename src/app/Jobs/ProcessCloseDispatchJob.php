<?php

declare(strict_types=1);

namespace App\Jobs;

class ProcessCloseDispatchJob extends BaseProcessJob
{
    public function __construct(
        string $idempotencyKey,
        string $source,
        string $type,
        string $scopeKey,
        array $payload,
        public string $dispatchId,
        ?string $commandId = null,
    ) {
        parent::__construct($idempotencyKey, $source, $type, $scopeKey, $payload, $commandId);
    }
}

