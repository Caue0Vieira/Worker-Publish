<?php

declare(strict_types=1);

namespace Domain\Outbox\Repositories;

use Domain\Outbox\Entities\OutboxEvent;

interface OutboxReadRepositoryInterface
{
    /**
     * @return array<OutboxEvent>
     */
    public function findPendingEvents(int $limit): array;
}

