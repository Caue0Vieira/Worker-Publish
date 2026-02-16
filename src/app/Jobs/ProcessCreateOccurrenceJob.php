<?php

declare(strict_types=1);

namespace App\Jobs;

class ProcessCreateOccurrenceJob extends BaseProcessJob
{
    public function __construct(
        string $idempotencyKey,
        string $source,
        string $type,
        string $scopeKey,
        array $payload,
        public string $externalId,
        public string $occurrenceType,
        public string $description,
        public string $reportedAt,
        ?string $commandId = null,
    ) {
        parent::__construct($idempotencyKey, $source, $type, $scopeKey, $payload, $commandId);
    }
}

