<?php

use App\Modules\EventOperations\Models\EventOperationEvent;
use App\Modules\EventOperations\Support\EventOperationsSnapshotBuilder;
use App\Modules\Events\Models\Event;

it('builds a versioned room snapshot from append only operation events', function () {
    $event = Event::factory()->active()->create([
        'title' => 'Casamento Ana e Bruno',
        'slug' => 'casamento-ana-bruno',
    ]);

    EventOperationEvent::factory()->create([
        'event_id' => $event->id,
        'event_sequence' => 1,
        'station_key' => 'intake',
        'event_key' => 'media.card.arrived',
        'severity' => 'info',
        'urgency' => 'normal',
        'title' => 'Midia recebida',
        'summary' => 'Recepcao recebeu uma nova midia.',
        'queue_depth' => 1,
        'station_load' => 0.30,
        'animation_hint' => 'intake_pulse',
        'render_group' => 'intake',
        'occurred_at' => '2026-04-12 18:40:00',
    ]);

    EventOperationEvent::factory()->create([
        'event_id' => $event->id,
        'event_sequence' => 2,
        'station_key' => 'human_review',
        'event_key' => 'media.moderation.pending',
        'severity' => 'warning',
        'urgency' => 'high',
        'title' => 'Fila humana crescente',
        'summary' => 'Doze midias aguardam revisao humana.',
        'queue_depth' => 12,
        'station_load' => 0.72,
        'animation_hint' => 'review_backlog',
        'render_group' => 'review',
        'occurred_at' => '2026-04-12 18:41:00',
    ]);

    EventOperationEvent::factory()->create([
        'event_id' => $event->id,
        'event_sequence' => 3,
        'station_key' => 'wall',
        'event_key' => 'wall.health.changed',
        'severity' => 'critical',
        'urgency' => 'critical',
        'title' => 'Wall em risco',
        'summary' => 'Um player parou de enviar heartbeat.',
        'queue_depth' => 0,
        'station_load' => 0.91,
        'animation_hint' => 'wall_health',
        'render_group' => 'wall',
        'payload_json' => [
            'current_item_id' => 'media_120',
            'next_item_id' => 'media_121',
            'online_players' => 1,
            'degraded_players' => 1,
            'offline_players' => 1,
        ],
        'occurred_at' => '2026-04-12 18:42:00',
    ]);

    $room = app(EventOperationsSnapshotBuilder::class)->buildForEvent($event, snapshotVersion: 3);

    $humanReviewStation = collect($room->stations)->firstWhere('station_key', 'human_review');
    $wallStation = collect($room->stations)->firstWhere('station_key', 'wall');
    $lastTimelineEntry = collect($room->timeline)->last();

    expect($room->schema_version)->toBe(1)
        ->and($room->snapshot_version)->toBe(3)
        ->and($room->event_sequence)->toBe(3)
        ->and($room->timeline_cursor)->toBe('evt_000003')
        ->and($room->health['status'])->toBe('risk')
        ->and($room->health['dominant_station_key'])->toBe('wall')
        ->and($humanReviewStation['queue_depth'])->toBe(12)
        ->and($wallStation['health'])->toBe('risk')
        ->and($lastTimelineEntry['event_sequence'])->toBe(3)
        ->and($room->wall['offline_players'])->toBe(1);
});

it('keeps animation runtime fields out of the snapshot payload', function () {
    $event = Event::factory()->active()->create();

    EventOperationEvent::factory()->create([
        'event_id' => $event->id,
        'event_sequence' => 1,
        'station_key' => 'gallery',
        'event_key' => 'media.published.gallery',
        'severity' => 'info',
        'title' => 'Midia publicada',
        'summary' => 'Uma midia entrou na galeria.',
    ]);

    $room = app(EventOperationsSnapshotBuilder::class)->buildForEvent($event, snapshotVersion: 1)->toArray();
    $galleryStation = collect($room['stations'])->firstWhere('station_key', 'gallery');

    expect($galleryStation)->not->toHaveKey('x')
        ->and($galleryStation)->not->toHaveKey('y')
        ->and($galleryStation)->not->toHaveKey('frame')
        ->and($galleryStation)->not->toHaveKey('position')
        ->and($room)->not->toHaveKey('scene_runtime');
});
