<?php

declare(strict_types=1);

namespace Domain\Idempotency\Repositories;

use Domain\Idempotency\Entities\Command;

interface CommandInboxReadRepositoryInterface
{
    public function findByCommandId(string $commandId): ?Command;
}

