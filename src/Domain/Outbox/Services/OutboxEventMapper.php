<?php

declare(strict_types=1);

namespace Domain\Outbox\Services;

use InvalidArgumentException;

class OutboxEventMapper
{
    private const EVENT_MAP = [
        'OccurrenceCreateRequested' => 'create_occurrence',
        'OccurrenceStartRequested' => 'start_occurrence',
        'OccurrenceResolvedRequested' => 'resolve_occurrence',
        'OccurrenceCancelledRequested' => 'cancel_occurrence',
        'DispatchCreateRequested' => 'create_dispatch',
        'DispatchCloseRequested' => 'close_dispatch',
        'DispatchStatusUpdateRequested' => 'update_dispatch_status',
    ];

    public function resolve(string $eventType): string
    {
        if (!isset(self::EVENT_MAP[$eventType])) {
            throw new InvalidArgumentException("Unsupported event type: {$eventType}");
        }

        return self::EVENT_MAP[$eventType];
    }

    public function isSupported(string $eventType): bool
    {
        return isset(self::EVENT_MAP[$eventType]);
    }
}

