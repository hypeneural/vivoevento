<?php

use App\Modules\EventOperations\Models\EventOperationEvent;
use App\Modules\Events\Models\Event;
use Illuminate\Support\Carbon;

it('persists append only event operation entries with json and datetime casts', function () {
    $event = Event::factory()->active()->create();

    $entry = EventOperationEvent::factory()->create([
        'event_id' => $event->id,
        'payload_json' => [
            'provider' => 'whatsapp',
            'recent_media_ids' => [101, 102],
        ],
        'occurred_at' => '2026-04-12 18:35:00',
    ]);

    expect($entry->getTable())->toBe('event_operation_events')
        ->and($entry->payload_json)->toBeArray()
        ->and($entry->payload_json['provider'])->toBe('whatsapp')
        ->and($entry->occurred_at)->toBeInstanceOf(Carbon::class)
        ->and($entry->event)->not->toBeNull()
        ->and($entry->event->is($event))->toBeTrue();
});

it('defines the append only indexes expected by the control room projection', function () {
    $indexes = match (\DB::getDriverName()) {
        'sqlite' => collect(\DB::select("PRAGMA index_list('event_operation_events')"))->pluck('name'),
        'pgsql' => collect(\DB::select("
            SELECT indexname
            FROM pg_indexes
            WHERE schemaname = 'public'
              AND tablename = 'event_operation_events'
        "))->pluck('indexname'),
        default => collect(),
    };

    expect($indexes)->toContain('event_operation_events_event_id_event_sequence_unique')
        ->and($indexes)->toContain('event_operation_events_event_id_occurred_at_index')
        ->and($indexes)->toContain('event_operation_events_event_id_station_key_occurred_at_index')
        ->and($indexes)->toContain('event_operation_events_event_id_correlation_key_index');
});
