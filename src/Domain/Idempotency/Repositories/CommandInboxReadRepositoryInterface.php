<?php

declare(strict_types=1);

namespace Domain\Idempotency\Repositories;

use Domain\Idempotency\Entities\CommandInBox;

interface CommandInboxReadRepositoryInterface
{
    public function findByCommandId(string $commandId): ?CommandInBox;
}

