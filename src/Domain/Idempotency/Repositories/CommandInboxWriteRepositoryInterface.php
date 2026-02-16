<?php

declare(strict_types=1);

namespace Domain\Idempotency\Repositories;

interface CommandInboxWriteRepositoryInterface
{
    public function markAsEnqueued(string $commandId): bool;
}
