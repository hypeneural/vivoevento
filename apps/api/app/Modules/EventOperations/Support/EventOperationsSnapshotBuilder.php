<?php

namespace App\Modules\EventOperations\Support;

use App\Modules\EventOperations\Data\EventOperationsRoomData;
use App\Modules\EventOperations\Data\EventOperationsStationData;
use App\Modules\EventOperations\Models\EventOperationEvent;
use App\Modules\Events\Enums\EventStatus;
use App\Modules\Events\Models\Event;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class EventOperationsSnapshotBuilder
{
    public const SCHEMA_VERSION = 1;

    private const STATION_LABELS = [
        'intake' => 'Recepcao',
        'download' => 'Download / Arquivo',
        'variants' => 'Laboratorio / Variantes',
        'safety' => 'Safety AI',
        'intelligence' => 'IA de contexto',
        'human_review' => 'Moderacao humana',
        'gallery' => 'Galeria',
        'wall' => 'Telao',
        'feedback' => 'Feedback',
        'alerts' => 'Alertas',
    ];

    private const STATION_RENDER_GROUPS = [
        'intake' => 'intake',
        'download' => 'processing',
        'variants' => 'processing',
        'safety' => 'review',
        'intelligence' => 'review',
        'human_review' => 'review',
        'gallery' => 'publishing',
        'wall' => 'wall',
        'feedback' => 'publishing',
        'alerts' => 'system',
    ];

    public function __construct(
        private readonly EventOperationsSequenceService $sequenceService,
    ) {}

    public function buildForEvent(Event $event, int $snapshotVersion): EventOperationsRoomData
    {
        /** @var Collection<int, EventOperationEvent> $entries */
        $entries = EventOperationEvent::query()
            ->where('event_id', $event->id)
            ->orderBy('event_sequence')
            ->get();

        $serverTime = now();
        $latestEntry = $entries->last();
        $latestSequence = (int) ($latestEntry?->event_sequence ?? 0);
        $timelineCursor = $latestSequence > 0
            ? $this->sequenceService->formatTimelineCursor($latestSequence)
            : null;

        $stations = collect(array_keys(self::STATION_LABELS))
            ->map(fn (string $stationKey) => $this->buildStationState($stationKey, $entries, $serverTime))
            ->values()
            ->all();

        $alerts = $this->buildAlerts($entries);
        $wall = $this->buildWallSummary($entries);
        $counters = $this->buildCounters($stations, $entries);
        $health = $this->buildHealthSummary($stations, $alerts, $serverTime);

        return new EventOperationsRoomData(
            schema_version: self::SCHEMA_VERSION,
            snapshot_version: $snapshotVersion,
            timeline_cursor: $timelineCursor,
            event_sequence: $latestSequence,
            server_time: $serverTime->toIso8601String(),
            event: [
                'id' => $event->id,
                'title' => $event->title,
                'slug' => $event->slug,
                'status' => $this->normalizeEventStatus($event),
                'timezone' => 'America/Sao_Paulo',
            ],
            health: $health,
            connection: [
                'status' => 'degraded',
                'realtime_connected' => false,
                'last_connected_at' => null,
                'last_resync_at' => $serverTime->toIso8601String(),
                'degraded_reason' => 'projection_only',
            ],
            counters: $counters,
            stations: $stations,
            alerts: $alerts,
            wall: $wall,
            timeline: $this->buildTimeline($entries),
        );
    }

    /**
     * @param Collection<int, EventOperationEvent> $entries
     * @return array<string, mixed>
     */
    private function buildStationState(string $stationKey, Collection $entries, Carbon $serverTime): array
    {
        /** @var Collection<int, EventOperationEvent> $stationEntries */
        $stationEntries = $entries->where('station_key', $stationKey)->values();
        $latestEntry = $stationEntries->last();
        $referenceTime = $latestEntry?->occurred_at ?? $serverTime;
        $queueDepth = (int) ($latestEntry?->queue_depth ?? 0);
        $stationLoad = round((float) ($latestEntry?->station_load ?? 0), 2);

        return (new EventOperationsStationData(
            station_key: $stationKey,
            label: self::STATION_LABELS[$stationKey],
            health: $this->deriveStationHealth($latestEntry, $queueDepth, $stationLoad),
            backlog_count: $queueDepth,
            queue_depth: $queueDepth,
            station_load: $stationLoad,
            throughput_per_minute: $stationEntries
                ->filter(fn (EventOperationEvent $entry) => $entry->occurred_at !== null
                    && $entry->occurred_at->betweenIncluded($referenceTime->copy()->subMinute(), $referenceTime))
                ->count(),
            recent_items: $stationEntries
                ->sortByDesc('event_sequence')
                ->take(3)
                ->map(fn (EventOperationEvent $entry) => $this->mapRecentItem($entry))
                ->values()
                ->all(),
            animation_hint: (string) ($latestEntry?->animation_hint ?: 'none'),
            render_group: (string) ($latestEntry?->render_group ?: self::STATION_RENDER_GROUPS[$stationKey]),
            dominant_reason: $latestEntry?->summary,
            updated_at: ($latestEntry?->occurred_at ?? $serverTime)->toIso8601String(),
        ))->toArray();
    }

    /**
     * @param Collection<int, EventOperationEvent> $entries
     * @return array<int, array<string, mixed>>
     */
    private function buildTimeline(Collection $entries): array
    {
        return $entries
            ->sortBy('event_sequence')
            ->take(-20)
            ->values()
            ->map(fn (EventOperationEvent $entry) => $this->mapTimelineEntry($entry))
            ->all();
    }

    /**
     * @param Collection<int, EventOperationEvent> $entries
     * @return array<int, array<string, mixed>>
     */
    private function buildAlerts(Collection $entries): array
    {
        return $entries
            ->filter(fn (EventOperationEvent $entry) => $entry->severity !== 'info' || $entry->station_key === 'alerts')
            ->sortByDesc('event_sequence')
            ->take(5)
            ->values()
            ->map(function (EventOperationEvent $entry): array {
                return [
                    'id' => $this->sequenceService->formatTimelineCursor((int) $entry->event_sequence),
                    'severity' => $entry->severity,
                    'urgency' => $entry->urgency,
                    'station_key' => $entry->station_key,
                    'title' => $entry->title,
                    'summary' => (string) ($entry->summary ?? ''),
                    'occurred_at' => $entry->occurred_at?->toIso8601String(),
                    'acknowledged_at' => null,
                ];
            })
            ->all();
    }

    /**
     * @param array<int, array<string, mixed>> $stations
     * @param Collection<int, EventOperationEvent> $entries
     * @return array<string, int>
     */
    private function buildCounters(array $stations, Collection $entries): array
    {
        return [
            'backlog_total' => collect($stations)->sum(fn (array $station) => (int) $station['queue_depth']),
            'human_review_pending' => (int) (collect($stations)->firstWhere('station_key', 'human_review')['queue_depth'] ?? 0),
            'processing_failures' => $entries->where('severity', 'critical')->count(),
            'intake_per_minute' => (int) (collect($stations)->firstWhere('station_key', 'intake')['throughput_per_minute'] ?? 0),
            'published_gallery_total' => $entries->where('event_key', 'media.published.gallery')->count(),
            'published_wall_total' => $entries->where('event_key', 'media.published.wall')->count(),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $stations
     * @param array<int, array<string, mixed>> $alerts
     * @return array<string, mixed>
     */
    private function buildHealthSummary(array $stations, array $alerts, Carbon $serverTime): array
    {
        $dominantStation = collect($stations)
            ->sortByDesc(fn (array $station) => $this->stationPriorityScore($station))
            ->first();

        $hasRisk = collect($stations)->contains(fn (array $station) => $station['health'] === 'risk')
            || collect($alerts)->contains(fn (array $alert) => $alert['severity'] === 'critical');
        $hasAttention = ! $hasRisk && (
            collect($stations)->contains(fn (array $station) => $station['health'] === 'attention')
            || collect($alerts)->contains(fn (array $alert) => $alert['severity'] === 'warning')
        );

        $status = $hasRisk ? 'risk' : ($hasAttention ? 'attention' : 'healthy');
        $dominantStationKey = $status === 'healthy' ? null : ($dominantStation['station_key'] ?? null);

        $summary = match ($status) {
            'risk' => $dominantStation ? "Risco em {$dominantStation['label']}" : 'Operacao em risco',
            'attention' => $dominantStation ? "Atencao em {$dominantStation['label']}" : 'Operacao em atencao',
            default => 'Operacao saudavel',
        };

        return [
            'status' => $status,
            'dominant_station_key' => $dominantStationKey,
            'summary' => $summary,
            'updated_at' => ($dominantStation['updated_at'] ?? $serverTime->toIso8601String()),
        ];
    }

    /**
     * @param Collection<int, EventOperationEvent> $entries
     * @return array<string, mixed>
     */
    private function buildWallSummary(Collection $entries): array
    {
        /** @var EventOperationEvent|null $wallEntry */
        $wallEntry = $entries
            ->where('station_key', 'wall')
            ->sortByDesc('event_sequence')
            ->first();

        $wallPayload = is_array($wallEntry?->payload_json) ? $wallEntry->payload_json : [];
        $wallHealth = $this->deriveStationHealth(
            $wallEntry,
            (int) ($wallEntry?->queue_depth ?? 0),
            round((float) ($wallEntry?->station_load ?? 0), 2),
        );

        return [
            'health' => $wallHealth,
            'online_players' => (int) ($wallPayload['online_players'] ?? 0),
            'degraded_players' => (int) ($wallPayload['degraded_players'] ?? 0),
            'offline_players' => (int) ($wallPayload['offline_players'] ?? 0),
            'current_item_id' => $wallPayload['current_item_id'] ?? null,
            'next_item_id' => $wallPayload['next_item_id'] ?? null,
            'confidence' => $wallEntry
                ? ($wallHealth === 'risk' ? 'low' : ($wallHealth === 'attention' ? 'medium' : 'high'))
                : 'unknown',
        ];
    }

    private function deriveStationHealth(?EventOperationEvent $entry, int $queueDepth, float $stationLoad): string
    {
        if (($entry?->severity ?? null) === 'critical') {
            return 'risk';
        }

        if (($entry?->severity ?? null) === 'warning' || $queueDepth > 0 || $stationLoad >= 0.6) {
            return 'attention';
        }

        return 'healthy';
    }

    /**
     * @param array<string, mixed> $station
     */
    private function stationPriorityScore(array $station): int
    {
        $healthScore = match ($station['health']) {
            'risk' => 500,
            'attention' => 200,
            'offline' => 100,
            default => 0,
        };

        return $healthScore
            + ((int) $station['queue_depth'] * 10)
            + (int) round(((float) $station['station_load']) * 100);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapTimelineEntry(EventOperationEvent $entry): array
    {
        return [
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
            'render_group' => $entry->render_group ?: self::STATION_RENDER_GROUPS[$entry->station_key],
            'animation_hint' => $entry->animation_hint ?: 'none',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRecentItem(EventOperationEvent $entry): array
    {
        return [
            'id' => $this->sequenceService->formatTimelineCursor((int) $entry->event_sequence),
            'event_sequence' => (int) $entry->event_sequence,
            'title' => $entry->title,
            'summary' => $entry->summary,
            'occurred_at' => $entry->occurred_at?->toIso8601String(),
            'event_media_id' => $entry->event_media_id,
            'preview_url' => is_array($entry->payload_json) ? ($entry->payload_json['preview_url'] ?? null) : null,
            'media_type' => is_array($entry->payload_json) ? ($entry->payload_json['media_type'] ?? null) : null,
        ];
    }

    private function normalizeEventStatus(Event $event): string
    {
        return match ($event->status) {
            EventStatus::Draft => 'draft',
            EventStatus::Paused => 'paused',
            EventStatus::Ended => 'ended',
            EventStatus::Archived => 'archived',
            default => 'live',
        };
    }
}
