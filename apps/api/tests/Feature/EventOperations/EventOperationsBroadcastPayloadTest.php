<?php

use App\Modules\EventOperations\Actions\AppendEventOperationEventAction;
use App\Modules\EventOperations\Events\EventOperationsAlertCreatedBroadcast;
use App\Modules\EventOperations\Events\EventOperationsHealthChangedBroadcast;
use App\Modules\EventOperations\Events\EventOperationsStationDeltaBroadcast;
use App\Modules\EventOperations\Events\EventOperationsTimelineAppendedBroadcast;
use App\Modules\Events\Models\Event;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\Event as EventFacade;

it('broadcasts compact queued operation payloads for non critical deltas', function () {
    EventFacade::fake([
        EventOperationsStationDeltaBroadcast::class,
        EventOperationsTimelineAppendedBroadcast::class,
        EventOperationsAlertCreatedBroadcast::class,
        EventOperationsHealthChangedBroadcast::class,
    ]);

    $domainEvent = Event::factory()->active()->create();

    app(AppendEventOperationEventAction::class)->execute($domainEvent, [
        'station_key' => 'human_review',
        'event_key' => 'media.moderation.rejected',
        'severity' => 'warning',
        'urgency' => 'high',
        'title' => 'Fila humana em atencao',
        'summary' => 'Quatro midias aguardam revisao.',
        'animation_hint' => 'review_warning',
        'render_group' => 'review',
        'queue_depth' => 4,
        'station_load' => 0.72,
        'correlation_key' => 'broadcast-payload-warning-001',
        'dedupe_window_key' => 'broadcast-payload-warning-window-001',
        'occurred_at' => '2026-04-12 19:10:00',
    ]);

    EventFacade::assertDispatched(
        EventOperationsStationDeltaBroadcast::class,
        function (EventOperationsStationDeltaBroadcast $broadcast) use ($domainEvent) {
            $payload = $broadcast->broadcastWith();

            return $broadcast->broadcastAs() === 'operations.station.delta'
                && $broadcast->broadcastOn()[0]->name === "private-event.{$domainEvent->id}.operations"
                && ! ($broadcast instanceof ShouldBroadcastNow)
                && $payload['schema_version'] === 1
                && $payload['snapshot_version'] === 1
                && $payload['timeline_cursor'] === 'evt_000001'
                && $payload['event_sequence'] === 1
                && isset($payload['station_delta']['station_key'])
                && ! array_key_exists('broadcast_priority', $payload)
                && ! array_key_exists('snapshot', $payload);
        },
    );

    EventFacade::assertDispatched(
        EventOperationsTimelineAppendedBroadcast::class,
        function (EventOperationsTimelineAppendedBroadcast $broadcast) use ($domainEvent) {
            $payload = $broadcast->broadcastWith();

            return $broadcast->broadcastAs() === 'operations.timeline.appended'
                && $broadcast->broadcastOn()[0]->name === "private-event.{$domainEvent->id}.operations"
                && $payload['schema_version'] === 1
                && $payload['snapshot_version'] === 1
                && $payload['event_sequence'] === 1
                && $payload['timeline_cursor'] === 'evt_000001'
                && ($payload['timeline_entry']['station_key'] ?? null) === 'human_review';
        },
    );

    EventFacade::assertNotDispatched(EventOperationsAlertCreatedBroadcast::class);
    EventFacade::assertNotDispatched(EventOperationsHealthChangedBroadcast::class);
});

it('broadcasts critical alert and health payloads with the versioned delta envelope', function () {
    EventFacade::fake([
        EventOperationsAlertCreatedBroadcast::class,
        EventOperationsHealthChangedBroadcast::class,
    ]);

    $domainEvent = Event::factory()->active()->create();

    app(AppendEventOperationEventAction::class)->execute($domainEvent, [
        'station_key' => 'wall',
        'event_key' => 'wall.health.changed',
        'severity' => 'critical',
        'urgency' => 'critical',
        'title' => 'Wall em risco',
        'summary' => 'Um player ficou offline.',
        'animation_hint' => 'wall_alert',
        'render_group' => 'wall',
        'payload_json' => [
            'online_players' => 1,
            'offline_players' => 1,
            'degraded_players' => 0,
        ],
        'correlation_key' => 'broadcast-payload-critical-001',
        'dedupe_window_key' => 'broadcast-payload-critical-window-001',
        'occurred_at' => '2026-04-12 19:11:00',
    ]);

    EventFacade::assertDispatched(
        EventOperationsAlertCreatedBroadcast::class,
        function (EventOperationsAlertCreatedBroadcast $broadcast) use ($domainEvent) {
            $payload = $broadcast->broadcastWith();

            return $broadcast->broadcastAs() === 'operations.alert.created'
                && $broadcast->broadcastOn()[0]->name === "private-event.{$domainEvent->id}.operations"
                && $broadcast instanceof ShouldBroadcastNow
                && $payload['schema_version'] === 1
                && $payload['snapshot_version'] === 1
                && $payload['event_sequence'] === 1
                && $payload['timeline_cursor'] === 'evt_000001'
                && ($payload['alert']['severity'] ?? null) === 'critical'
                && ! array_key_exists('timeline_entry', $payload);
        },
    );

    EventFacade::assertDispatched(
        EventOperationsHealthChangedBroadcast::class,
        function (EventOperationsHealthChangedBroadcast $broadcast) use ($domainEvent) {
            $payload = $broadcast->broadcastWith();

            return $broadcast->broadcastAs() === 'operations.health.changed'
                && $broadcast->broadcastOn()[0]->name === "private-event.{$domainEvent->id}.operations"
                && $broadcast instanceof ShouldBroadcastNow
                && $payload['schema_version'] === 1
                && $payload['snapshot_version'] === 1
                && $payload['event_sequence'] === 1
                && $payload['timeline_cursor'] === 'evt_000001'
                && ($payload['health']['status'] ?? null) === 'risk';
        },
    );
});
