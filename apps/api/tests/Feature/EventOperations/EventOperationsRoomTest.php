<?php

use App\Modules\EventOperations\Actions\AppendEventOperationEventAction;
use App\Modules\Events\Models\Event;

function seedEventOperationsRoom(Event $event): void
{
    $append = app(AppendEventOperationEventAction::class);

    $append->execute($event, [
        'station_key' => 'intake',
        'event_key' => 'media.card.arrived',
        'severity' => 'info',
        'urgency' => 'normal',
        'title' => 'Midia recebida',
        'summary' => 'Uma nova midia entrou via WhatsApp.',
        'animation_hint' => 'intake_pulse',
        'render_group' => 'intake',
        'correlation_key' => 'room_corr_001',
        'dedupe_window_key' => 'room_001',
        'occurred_at' => '2026-04-12 18:40:00',
    ]);

    $append->execute($event, [
        'station_key' => 'wall',
        'event_key' => 'wall.health.changed',
        'severity' => 'critical',
        'urgency' => 'critical',
        'title' => 'Wall em risco',
        'summary' => 'Um player do wall ficou offline.',
        'animation_hint' => 'wall_health',
        'render_group' => 'wall',
        'payload_json' => [
            'current_item_id' => 'media_120',
            'next_item_id' => 'media_121',
            'online_players' => 1,
            'degraded_players' => 1,
            'offline_players' => 1,
        ],
        'correlation_key' => 'room_corr_002',
        'dedupe_window_key' => 'room_002',
        'occurred_at' => '2026-04-12 18:41:00',
    ]);
}

it('returns the event operations room snapshot for an authorized operator', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'title' => 'Casamento Ana e Bruno',
        'slug' => 'casamento-ana-bruno',
    ]);

    seedEventOperationsRoom($event);

    $response = $this->apiGet("/events/{$event->id}/operations/room");

    $this->assertApiSuccess($response);

    $response->assertJsonStructure([
        'success',
        'data' => [
            'schema_version',
            'snapshot_version',
            'timeline_cursor',
            'event_sequence',
            'server_time',
            'event' => ['id', 'title', 'slug', 'status', 'timezone'],
            'health' => ['status', 'dominant_station_key', 'summary', 'updated_at'],
            'connection' => ['status', 'realtime_connected', 'last_resync_at'],
            'counters' => [
                'backlog_total',
                'human_review_pending',
                'processing_failures',
                'intake_per_minute',
                'published_gallery_total',
                'published_wall_total',
            ],
            'stations',
            'alerts',
            'wall' => ['health', 'online_players', 'degraded_players', 'offline_players', 'current_item_id', 'next_item_id', 'confidence'],
            'timeline',
        ],
        'meta' => ['request_id'],
    ]);

    $room = $response->json('data');
    $wallStation = collect($room['stations'])->firstWhere('station_key', 'wall');

    expect($room['schema_version'])->toBe(1)
        ->and($room['snapshot_version'])->toBe(2)
        ->and($room['timeline_cursor'])->toBe('evt_000002')
        ->and($room['event_sequence'])->toBe(2)
        ->and($room['health']['status'])->toBe('risk')
        ->and($room['health']['dominant_station_key'])->toBe('wall')
        ->and($room['wall']['offline_players'])->toBe(1)
        ->and($wallStation['health'])->toBe('risk')
        ->and($wallStation)->not->toHaveKey('x')
        ->and($wallStation)->not->toHaveKey('frame');
});

it('rebuilds the room snapshot on demand when append-only events exist without a materialized snapshot', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    \App\Modules\EventOperations\Models\EventOperationEvent::factory()->create([
        'event_id' => $event->id,
        'event_sequence' => 1,
        'station_key' => 'gallery',
        'event_key' => 'media.published.gallery',
        'severity' => 'info',
        'title' => 'Midia publicada',
        'summary' => 'Uma nova midia entrou na galeria.',
    ]);

    $response = $this->apiGet("/events/{$event->id}/operations/room");

    $this->assertApiSuccess($response);

    $response->assertJsonPath('data.snapshot_version', 1)
        ->assertJsonPath('data.event_sequence', 1)
        ->assertJsonPath('data.timeline_cursor', 'evt_000001');
});
