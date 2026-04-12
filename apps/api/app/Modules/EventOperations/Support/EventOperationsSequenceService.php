<?php

namespace App\Modules\EventOperations\Support;

use App\Modules\EventOperations\Models\EventOperationEvent;
use App\Modules\Events\Models\Event;

class EventOperationsSequenceService
{
    public function nextSequenceForEvent(Event|int $event): int
    {
        $eventId = $this->resolveEventId($event);

        return ((int) EventOperationEvent::query()
            ->where('event_id', $eventId)
            ->max('event_sequence')) + 1;
    }

    public function formatTimelineCursor(int $eventSequence): string
    {
        return 'evt_' . str_pad((string) $eventSequence, 6, '0', STR_PAD_LEFT);
    }

    public function parseTimelineCursor(?string $cursor): ?int
    {
        if (! is_string($cursor) || ! preg_match('/^evt_(\d{6,})$/', $cursor, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    public function findIdempotentEvent(
        Event|int $event,
        string $stationKey,
        string $eventKey,
        ?string $correlationKey = null,
        ?string $dedupeWindowKey = null,
    ): ?EventOperationEvent {
        if (blank($correlationKey) && blank($dedupeWindowKey)) {
            return null;
        }

        $query = EventOperationEvent::query()
            ->where('event_id', $this->resolveEventId($event))
            ->where('station_key', $stationKey)
            ->where('event_key', $eventKey);

        if (! blank($correlationKey)) {
            $query->where('correlation_key', $correlationKey);
        }

        if (! blank($dedupeWindowKey)) {
            $query->where('dedupe_window_key', $dedupeWindowKey);
        }

        return $query->orderByDesc('event_sequence')->first();
    }

    private function resolveEventId(Event|int $event): int
    {
        return $event instanceof Event ? (int) $event->getKey() : $event;
    }
}
