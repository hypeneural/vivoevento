<?php

use App\Modules\EventOperations\Actions\AppendEventOperationEventAction;
use App\Modules\EventOperations\Events\EventOperationsAlertCreatedBroadcast;
use App\Modules\EventOperations\Events\EventOperationsHealthChangedBroadcast;
use App\Modules\EventOperations\Events\EventOperationsStationDeltaBroadcast;
use App\Modules\EventOperations\Events\EventOperationsTimelineAppendedBroadcast;
use App\Modules\EventOperations\Models\EventOperationEvent;
use App\Modules\Events\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event as EventFacade;

it('dispatches queued and immediate operations broadcasts after a committed append', function () {
    EventFacade::fake([
        EventOperationsStationDeltaBroadcast::class,
        EventOperationsTimelineAppendedBroadcast::class,
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
        'correlation_key' => 'broadcast-after-commit-001',
        'dedupe_window_key' => 'broadcast-after-commit-window-001',
        'occurred_at' => '2026-04-12 19:00:00',
    ]);

    EventFacade::assertDispatched(EventOperationsStationDeltaBroadcast::class);
    EventFacade::assertDispatched(EventOperationsTimelineAppendedBroadcast::class);
    EventFacade::assertDispatched(EventOperationsAlertCreatedBroadcast::class);
    EventFacade::assertDispatched(EventOperationsHealthChangedBroadcast::class);
});

it('does not dispatch operations broadcasts when the outer transaction rolls back', function () {
    EventFacade::fake([
        EventOperationsStationDeltaBroadcast::class,
        EventOperationsTimelineAppendedBroadcast::class,
        EventOperationsAlertCreatedBroadcast::class,
        EventOperationsHealthChangedBroadcast::class,
    ]);

    $domainEvent = Event::factory()->active()->create();

    try {
        DB::transaction(function () use ($domainEvent) {
            app(AppendEventOperationEventAction::class)->execute($domainEvent, [
                'station_key' => 'wall',
                'event_key' => 'wall.health.changed',
                'severity' => 'critical',
                'urgency' => 'critical',
                'title' => 'Wall em risco',
                'summary' => 'Um player ficou offline.',
                'animation_hint' => 'wall_alert',
                'render_group' => 'wall',
                'correlation_key' => 'broadcast-after-commit-rollback-001',
                'dedupe_window_key' => 'broadcast-after-commit-rollback-window-001',
                'occurred_at' => '2026-04-12 19:01:00',
            ]);

            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }

    EventFacade::assertNotDispatched(EventOperationsStationDeltaBroadcast::class);
    EventFacade::assertNotDispatched(EventOperationsTimelineAppendedBroadcast::class);
    EventFacade::assertNotDispatched(EventOperationsAlertCreatedBroadcast::class);
    EventFacade::assertNotDispatched(EventOperationsHealthChangedBroadcast::class);

    expect(EventOperationEvent::query()->count())->toBe(0);
});
