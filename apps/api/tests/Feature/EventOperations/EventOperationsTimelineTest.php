<?php

use App\Modules\EventOperations\Actions\AppendEventOperationEventAction;
use App\Modules\Events\Models\Event;
use App\Modules\MediaProcessing\Models\EventMedia;

function seedEventOperationsTimeline(Event $event): void
{
    $append = app(AppendEventOperationEventAction::class);
    $eventMedia = EventMedia::factory()->createQuietly([
        'event_id' => $event->id,
    ]);

    $append->execute($event, [
        'station_key' => 'intake',
        'event_key' => 'media.card.arrived',
        'severity' => 'info',
        'urgency' => 'normal',
        'title' => 'Midia recebida',
        'summary' => 'Recepcao recebeu uma nova midia.',
        'render_group' => 'intake',
        'correlation_key' => 'timeline_corr_001',
        'dedupe_window_key' => 'timeline_001',
        'occurred_at' => '2026-04-12 18:40:00',
    ]);

    $append->execute($event, [
        'station_key' => 'human_review',
        'event_key' => 'media.moderation.pending',
        'severity' => 'warning',
        'urgency' => 'high',
        'title' => 'Fila humana crescendo',
        'summary' => 'Quatro midias aguardam revisao.',
        'render_group' => 'review',
        'event_media_id' => $eventMedia->id,
        'queue_depth' => 4,
        'correlation_key' => 'timeline_corr_002',
        'dedupe_window_key' => 'timeline_002',
        'occurred_at' => '2026-04-12 18:41:00',
    ]);

    $append->execute($event, [
        'station_key' => 'wall',
        'event_key' => 'wall.health.changed',
        'severity' => 'critical',
        'urgency' => 'critical',
        'title' => 'Wall em risco',
        'summary' => 'Um player do wall ficou offline.',
        'render_group' => 'wall',
        'correlation_key' => 'timeline_corr_003',
        'dedupe_window_key' => 'timeline_003',
        'occurred_at' => '2026-04-12 18:42:00',
    ]);
}

it('returns append-only timeline entries ordered from oldest to newest after a cursor', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    seedEventOperationsTimeline($event);

    $response = $this->apiGet("/events/{$event->id}/operations/timeline?cursor=evt_000001");

    $this->assertApiSuccess($response);

    $response->assertJsonStructure([
        'success',
        'data' => [
            'schema_version',
            'snapshot_version',
            'timeline_cursor',
            'event_sequence',
            'server_time',
            'entries',
            'filters' => ['cursor', 'station_key', 'severity', 'event_media_id', 'limit'],
        ],
        'meta' => ['request_id'],
    ]);

    $entries = $response->json('data.entries');

    expect($response->json('data.snapshot_version'))->toBe(3)
        ->and($response->json('data.timeline_cursor'))->toBe('evt_000003')
        ->and($response->json('data.event_sequence'))->toBe(3)
        ->and($entries)->toHaveCount(2)
        ->and($entries[0]['event_sequence'])->toBe(2)
        ->and($entries[1]['event_sequence'])->toBe(3);
});

it('filters the append-only timeline by station severity and media id', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    seedEventOperationsTimeline($event);

    $eventMediaId = EventMedia::query()->where('event_id', $event->id)->value('id');

    $response = $this->apiGet("/events/{$event->id}/operations/timeline?station_key=human_review&severity=warning&event_media_id={$eventMediaId}");

    $this->assertApiSuccess($response);

    $entries = $response->json('data.entries');

    expect($entries)->toHaveCount(1)
        ->and($entries[0]['station_key'])->toBe('human_review')
        ->and($entries[0]['severity'])->toBe('warning')
        ->and($entries[0]['event_media_id'])->toBe($eventMediaId);
});
