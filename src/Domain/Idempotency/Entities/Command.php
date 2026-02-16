<?php

declare(strict_types=1);

namespace Domain\Idempotency\Entities;

use DateTimeImmutable;
use Domain\Shared\ValueObjects\Uuid;
use Exception;

class Command
{
    private function __construct(
        private readonly Uuid $id,
        private readonly string $idempotencyKey,
        private readonly string $source,
        private readonly string $type,
        private readonly string $scopeKey,
        private readonly string $payloadHash,
        private readonly string|array $payload,
        private readonly string $status,
        private readonly ?string $result,
        private readonly ?string $errorMessage,
        private readonly ?DateTimeImmutable $processedAt,
        private readonly ?DateTimeImmutable $expiresAt,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id->toString(),
            'idempotency_key' => $this->idempotencyKey,
            'source' => $this->source,
            'type' => $this->type,
            'scope_key' => $this->scopeKey,
            'payload_hash' => $this->payloadHash,
            'payload' => $this->payload,
            'status' => $this->status,
            'result' => $this->result,
            'error_message' => $this->errorMessage,
            'processed_at' => $this->processedAt?->format('Y-m-d H:i:s'),
            'expires_at' => $this->expiresAt?->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @throws Exception
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Uuid::fromString($data['id']),
            idempotencyKey: $data['idempotency_key'],
            source: $data['source'],
            type: $data['type'],
            scopeKey: $data['scope_key'],
            payloadHash: $data['payload_hash'],
            payload: $data['payload'],
            status: $data['status'],
            result: $data['result'] ?? null,
            errorMessage: $data['error_message'] ?? null,
            processedAt: isset($data['processed_at']) ? new DateTimeImmutable($data['processed_at']) : null,
            expiresAt: isset($data['expires_at']) ? new DateTimeImmutable($data['expires_at']) : null,
            createdAt: new DateTimeImmutable($data['created_at']),
            updatedAt: new DateTimeImmutable($data['updated_at']),
        );
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function idempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function scopeKey(): string
    {
        return $this->scopeKey;
    }

    public function payloadHash(): string
    {
        return $this->payloadHash;
    }

    public function payload(): string|array
    {
        return $this->payload;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function result(): ?string
    {
        return $this->result;
    }

    public function errorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function processedAt(): ?DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function expiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
