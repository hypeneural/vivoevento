<?php

use App\Modules\EventOperations\Actions\AppendEventOperationEventAction;
use App\Modules\EventOperations\Data\EventOperationsDeltaData;
use App\Modules\EventOperations\Models\EventOperationEvent;
use App\Modules\EventOperations\Models\EventOperationSnapshot;
use App\Modules\Events\Models\Event;

it('appends a new operation event rebuilds the snapshot and returns the delta contract', function () {
    $event = Event::factory()->active()->create([
        'title' => 'Operacao Casamento',
        'slug' => 'operacao-casamento',
    ]);

    $result = app(AppendEventOperationEventAction::class)->execute($event, [
        'station_key' => 'intake',
        'event_key' => 'media.card.arrived',
        'severity' => 'info',
        'urgency' => 'normal',
        'title' => 'Midia recebida',
        'summary' => 'Uma nova midia entrou via WhatsApp.',
        'payload_json' => [
            'provider' => 'whatsapp',
            'media_type' => 'image',
        ],
        'animation_hint' => 'intake_pulse',
        'station_load' => 0.35,
        'queue_depth' => 1,
        'render_group' => 'intake',
        'dedupe_window_key' => 'intake_001',
        'correlation_key' => 'corr_intake_001',
        'occurred_at' => '2026-04-12 18:40:00',
    ]);

    expect($result['was_idempotent'])->toBeFalse()
        ->and($result['entry'])->toBeInstanceOf(EventOperationEvent::class)
        ->and($result['snapshot'])->toBeInstanceOf(EventOperationSnapshot::class)
        ->and($result['delta'])->toBeInstanceOf(EventOperationsDeltaData::class)
        ->and($result['entry']->event_sequence)->toBe(1)
        ->and($result['snapshot']->snapshot_version)->toBe(1)
        ->and($result['snapshot']->latest_event_sequence)->toBe(1)
        ->and($result['delta']->event_sequence)->toBe(1)
        ->and($result['delta']->snapshot_version)->toBe(1)
        ->and($result['delta']->timeline_cursor)->toBe('evt_000001')
        ->and($result['delta']->kind)->toBe('timeline.appended');
});

it('treats repeated deltas as idempotent without creating a new append only row', function () {
    $event = Event::factory()->active()->create();
    $action = app(AppendEventOperationEventAction::class);

    $first = $action->execute($event, [
        'station_key' => 'gallery',
        'event_key' => 'media.published.gallery',
        'severity' => 'info',
        'urgency' => 'normal',
        'title' => 'Midia publicada',
        'summary' => 'A galeria recebeu uma nova midia.',
        'animation_hint' => 'gallery_publish',
        'render_group' => 'publishing',
        'dedupe_window_key' => 'gallery_publish_001',
        'correlation_key' => 'corr_gallery_publish_001',
        'occurred_at' => '2026-04-12 18:45:00',
    ]);

    $duplicate = $action->execute($event, [
        'station_key' => 'gallery',
        'event_key' => 'media.published.gallery',
        'severity' => 'info',
        'urgency' => 'normal',
        'title' => 'Midia publicada',
        'summary' => 'A galeria recebeu uma nova midia.',
        'animation_hint' => 'gallery_publish',
        'render_group' => 'publishing',
        'dedupe_window_key' => 'gallery_publish_001',
        'correlation_key' => 'corr_gallery_publish_001',
        'occurred_at' => '2026-04-12 18:45:00',
    ]);

    expect(EventOperationEvent::query()->where('event_id', $event->id)->count())->toBe(1)
        ->and($duplicate['was_idempotent'])->toBeTrue()
        ->and($duplicate['entry']->is($first['entry']))->toBeTrue()
        ->and($duplicate['snapshot']->snapshot_version)->toBe(1)
        ->and($duplicate['delta']->event_sequence)->toBe(1);
});
