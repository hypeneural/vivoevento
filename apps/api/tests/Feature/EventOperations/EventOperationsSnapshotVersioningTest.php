<?php

use App\Modules\EventOperations\Actions\AppendEventOperationEventAction;
use App\Modules\EventOperations\Models\EventOperationSnapshot;
use App\Modules\Events\Models\Event;

it('increments the snapshot version when new operation events are appended', function () {
    $event = Event::factory()->active()->create();
    $append = app(AppendEventOperationEventAction::class);

    $append->execute($event, [
        'station_key' => 'intake',
        'event_key' => 'media.card.arrived',
        'severity' => 'info',
        'urgency' => 'normal',
        'title' => 'Midia recebida',
        'summary' => 'Recepcao recebeu uma nova midia.',
        'animation_hint' => 'intake_pulse',
        'render_group' => 'intake',
        'correlation_key' => 'corr_seq_001',
        'dedupe_window_key' => 'seq_001',
        'occurred_at' => '2026-04-12 18:40:00',
    ]);

    $append->execute($event, [
        'station_key' => 'human_review',
        'event_key' => 'media.moderation.pending',
        'severity' => 'warning',
        'urgency' => 'high',
        'title' => 'Fila humana crescente',
        'summary' => 'Quatro midias aguardam revisao.',
        'animation_hint' => 'review_backlog',
        'render_group' => 'review',
        'queue_depth' => 4,
        'station_load' => 0.64,
        'correlation_key' => 'corr_seq_002',
        'dedupe_window_key' => 'seq_002',
        'occurred_at' => '2026-04-12 18:41:00',
    ]);

    $snapshot = EventOperationSnapshot::query()->where('event_id', $event->id)->firstOrFail();
    $humanReviewStation = collect($snapshot->snapshot_json['stations'])->firstWhere('station_key', 'human_review');

    expect($snapshot->schema_version)->toBe(1)
        ->and($snapshot->snapshot_version)->toBe(2)
        ->and($snapshot->latest_event_sequence)->toBe(2)
        ->and($snapshot->timeline_cursor)->toBe('evt_000002')
        ->and($snapshot->server_time)->not->toBeNull()
        ->and($snapshot->snapshot_json['health']['status'])->toBe('attention')
        ->and($humanReviewStation['queue_depth'])->toBe(4)
        ->and($humanReviewStation)->not->toHaveKey('frame')
        ->and($humanReviewStation)->not->toHaveKey('x');
});
