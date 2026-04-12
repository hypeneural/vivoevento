<?php

namespace App\Modules\EventOperations\Actions;

use App\Modules\EventOperations\Data\EventOperationsDeltaData;
use App\Modules\EventOperations\Events\EventOperationsAlertCreatedBroadcast;
use App\Modules\EventOperations\Events\EventOperationsHealthChangedBroadcast;
use App\Modules\EventOperations\Events\EventOperationsStationDeltaBroadcast;
use App\Modules\EventOperations\Events\EventOperationsTimelineAppendedBroadcast;
use App\Modules\EventOperations\Models\EventOperationEvent;
use App\Modules\EventOperations\Models\EventOperationSnapshot;
use App\Modules\EventOperations\Support\EventOperationsAttentionPriority;
use App\Modules\EventOperations\Support\EventOperationsBroadcastPriority;
use App\Modules\EventOperations\Support\EventOperationsSequenceService;
use App\Modules\Events\Models\Event;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AppendEventOperationEventAction
{
    public function __construct(
        private readonly EventOperationsSequenceService $sequenceService,
        private readonly RebuildEventOperationsSnapshotAction $rebuildSnapshot,
        private readonly EventOperationsBroadcastPriority $broadcastPriority,
    ) {}

    /**
     * @param array<string, mixed> $payload
     * @return array{
     *   entry: EventOperationEvent,
     *   snapshot: EventOperationSnapshot,
     *   delta: EventOperationsDeltaData,
     *   was_idempotent: bool
     * }
     */
    public function execute(Event $event, array $payload): array
    {
        return DB::transaction(function () use ($event, $payload) {
            $existing = $this->sequenceService->findIdempotentEvent(
                $event,
                stationKey: (string) $payload['station_key'],
                eventKey: (string) $payload['event_key'],
                correlationKey: $payload['correlation_key'] ?? null,
                dedupeWindowKey: $payload['dedupe_window_key'] ?? null,
            );

            if ($existing) {
                $snapshot = EventOperationSnapshot::query()->where('event_id', $event->id)->first()
                    ?? $this->rebuildSnapshot->execute($event);

                return [
                    'entry' => $existing,
                    'snapshot' => $snapshot,
                    'delta' => $this->buildDelta($existing, $snapshot),
                    'was_idempotent' => true,
                ];
            }

            $entry = EventOperationEvent::query()->create([
                'event_id' => $event->id,
                'event_media_id' => $payload['event_media_id'] ?? null,
                'inbound_message_id' => $payload['inbound_message_id'] ?? null,
                'station_key' => (string) $payload['station_key'],
                'event_key' => (string) $payload['event_key'],
                'severity' => (string) ($payload['severity'] ?? 'info'),
                'urgency' => (string) ($payload['urgency'] ?? 'normal'),
                'title' => (string) $payload['title'],
                'summary' => $payload['summary'] ?? null,
                'payload_json' => $payload['payload_json'] ?? null,
                'animation_hint' => $payload['animation_hint'] ?? null,
                'station_load' => isset($payload['station_load']) ? (float) $payload['station_load'] : null,
                'queue_depth' => (int) ($payload['queue_depth'] ?? 0),
                'render_group' => $payload['render_group'] ?? null,
                'dedupe_window_key' => $payload['dedupe_window_key'] ?? null,
                'correlation_key' => $payload['correlation_key'] ?? null,
                'event_sequence' => $this->sequenceService->nextSequenceForEvent($event),
                'occurred_at' => isset($payload['occurred_at'])
                    ? Carbon::parse((string) $payload['occurred_at'])
                    : now(),
            ]);

            $snapshot = $this->rebuildSnapshot->execute($event);
            $delta = $this->buildDelta($entry, $snapshot);

            $this->dispatchRealtime($event, $delta);

            return [
                'entry' => $entry,
                'snapshot' => $snapshot,
                'delta' => $delta,
                'was_idempotent' => false,
            ];
        });
    }

    private function buildDelta(
        EventOperationEvent $entry,
        EventOperationSnapshot $snapshot,
    ): EventOperationsDeltaData {
        $stationState = collect($snapshot->snapshot_json['stations'] ?? [])
            ->firstWhere('station_key', $entry->station_key);

        $timelineEntry = [
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
        ];

        return new EventOperationsDeltaData(
            schema_version: (int) $snapshot->schema_version,
            snapshot_version: (int) $snapshot->snapshot_version,
            timeline_cursor: $snapshot->timeline_cursor,
            event_sequence: (int) $entry->event_sequence,
            server_time: ($snapshot->server_time ?? now())->toIso8601String(),
            kind: 'timeline.appended',
            broadcast_priority: $this->resolveBroadcastPriority($entry->severity, $entry->urgency),
            station_delta: $stationState ? [
                'station_key' => $entry->station_key,
                'patch' => collect($stationState)->only([
                    'health',
                    'backlog_count',
                    'queue_depth',
                    'station_load',
                    'throughput_per_minute',
                    'recent_items',
                    'animation_hint',
                    'dominant_reason',
                    'updated_at',
                ])->all(),
            ] : null,
            timeline_entry: $timelineEntry,
            alert: $entry->severity !== 'info' ? [
                'id' => $this->sequenceService->formatTimelineCursor((int) $entry->event_sequence),
                'severity' => $entry->severity,
                'urgency' => $entry->urgency,
                'station_key' => $entry->station_key,
                'title' => $entry->title,
                'summary' => (string) ($entry->summary ?? ''),
                'occurred_at' => $entry->occurred_at?->toIso8601String(),
                'acknowledged_at' => null,
            ] : null,
            health: $snapshot->snapshot_json['health'] ?? null,
            snapshot: null,
            resync_required: false,
        );
    }

    private function resolveBroadcastPriority(string $severity, string $urgency): string
    {
        if ($severity === 'critical' || $urgency === 'critical') {
            return EventOperationsAttentionPriority::CRITICAL_IMMEDIATE;
        }

        if ($severity === 'warning' || $urgency === 'high') {
            return EventOperationsAttentionPriority::OPERATIONAL_NORMAL;
        }

        return EventOperationsAttentionPriority::TIMELINE_COALESCIBLE;
    }

    private function dispatchRealtime(Event $event, EventOperationsDeltaData $delta): void
    {
        if ($this->broadcastPriority->shouldBroadcastStationDelta($delta)) {
            event(new EventOperationsStationDeltaBroadcast($event->id, $delta));
        }

        if ($this->broadcastPriority->shouldBroadcastTimeline($delta)) {
            event(new EventOperationsTimelineAppendedBroadcast($event->id, $delta));
        }

        if ($this->broadcastPriority->shouldBroadcastAlert($delta)) {
            event(new EventOperationsAlertCreatedBroadcast($event->id, $delta));
        }

        if ($this->broadcastPriority->shouldBroadcastHealth($delta)) {
            event(new EventOperationsHealthChangedBroadcast($event->id, $delta));
        }
    }
}
