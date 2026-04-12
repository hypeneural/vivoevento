<?php

namespace App\Modules\EventOperations\Actions;

use App\Modules\EventOperations\Models\EventOperationEvent;
use App\Modules\EventOperations\Models\EventOperationSnapshot;
use App\Modules\EventOperations\Support\EventOperationsSequenceService;
use App\Modules\Events\Models\Event;
use Illuminate\Support\Collection;

class BuildEventOperationsTimelineAction
{
    public function __construct(
        private readonly EventOperationsSequenceService $sequenceService,
        private readonly RebuildEventOperationsSnapshotAction $rebuildSnapshot,
    ) {}

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function execute(Event $event, array $filters = []): array
    {
        $snapshot = EventOperationSnapshot::query()
            ->where('event_id', $event->id)
            ->first();

        if (! $snapshot) {
            $snapshot = $this->rebuildSnapshot->execute($event);
        }

        $cursorSequence = $this->sequenceService->parseTimelineCursor($filters['cursor'] ?? null);
        $limit = (int) ($filters['limit'] ?? 50);

        $query = EventOperationEvent::query()
            ->where('event_id', $event->id)
            ->orderBy('event_sequence');

        if ($cursorSequence !== null) {
            $query->where('event_sequence', '>', $cursorSequence);
        }

        if (filled($filters['station_key'] ?? null)) {
            $query->where('station_key', $filters['station_key']);
        }

        if (filled($filters['severity'] ?? null)) {
            $query->where('severity', $filters['severity']);
        }

        if (filled($filters['event_media_id'] ?? null)) {
            $query->where('event_media_id', (int) $filters['event_media_id']);
        }

        /** @var Collection<int, EventOperationEvent> $entries */
        $entries = $cursorSequence === null
            ? $query->latest('event_sequence')->limit($limit)->get()->sortBy('event_sequence')->values()
            : $query->limit($limit)->get()->values();

        $lastEntry = $entries->last();

        return [
            'schema_version' => (int) $snapshot->schema_version,
            'snapshot_version' => (int) $snapshot->snapshot_version,
            'timeline_cursor' => $lastEntry
                ? $this->sequenceService->formatTimelineCursor((int) $lastEntry->event_sequence)
                : $snapshot->timeline_cursor,
            'event_sequence' => $lastEntry
                ? (int) $lastEntry->event_sequence
                : (int) $snapshot->latest_event_sequence,
            'server_time' => ($snapshot->server_time ?? now())->toIso8601String(),
            'entries' => $entries->map(fn (EventOperationEvent $entry) => [
                'id' => $this->sequenceService->formatTimelineCursor((int) $entry->event_sequence),
                'event_sequence' => (int) $entry->event_sequence,
                'station_key' => $entry->station_key,
                'event_key' => $entry->event_key,
                'severity' => $entry->severity,
                'urgency' => $entry->urgency,
                'title' => $entry->title,
                'summary' => (string) ($entry->summary ?? ''),
                'occurred_at' => $entry->occurred_at?->toIso8601String(),
                'correlation_key' => $entry->correlation_key,
                'event_media_id' => $entry->event_media_id,
                'inbound_message_id' => $entry->inbound_message_id,
                'render_group' => $entry->render_group,
                'animation_hint' => $entry->animation_hint,
            ])->all(),
            'filters' => [
                'cursor' => $filters['cursor'] ?? null,
                'station_key' => $filters['station_key'] ?? null,
                'severity' => $filters['severity'] ?? null,
                'event_media_id' => isset($filters['event_media_id']) ? (int) $filters['event_media_id'] : null,
                'limit' => $limit,
            ],
        ];
    }
}
