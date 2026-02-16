<?php

declare(strict_types=1);

namespace Domain\Outbox\Entities;

use DateTimeImmutable;
use Domain\Shared\ValueObjects\Uuid;
use Exception;

class OutboxEvent
{
    private function __construct(
        private readonly Uuid $id,
        private readonly string $aggregateType,
        private readonly string $aggregateId,
        private readonly string $eventType,
        private readonly string $status,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        string $aggregateType,
        string $aggregateId,
        string $eventType,
        string $status,
        DateTimeImmutable $createdAt
    ): self {
        return new self(
            id: Uuid::generate(),
            aggregateType: $aggregateType,
            aggregateId: $aggregateId,
            eventType: $eventType,
            status: $status,
            createdAt: $createdAt,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id->toString(),
            'aggregate_type' => $this->aggregateType,
            'aggregate_id' => $this->aggregateId,
            'event_type' => $this->eventType,
            'status' => $this->status,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @throws Exception
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Uuid::fromString($data['id']),
            aggregateType: $data['aggregate_type'],
            aggregateId: $data['aggregate_id'],
            eventType: $data['event_type'],
            status: $data['status'],
            createdAt: new DateTimeImmutable($data['created_at']),
        );
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function aggregateType(): string
    {
        return $this->aggregateType;
    }

    public function aggregateId(): string
    {
        return $this->aggregateId;
    }

    public function eventType(): string
    {
        return $this->eventType;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}

