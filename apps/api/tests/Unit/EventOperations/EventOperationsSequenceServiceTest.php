<?php

use App\Modules\EventOperations\Models\EventOperationEvent;
use App\Modules\EventOperations\Support\EventOperationsSequenceService;
use App\Modules\Events\Models\Event;

it('allocates monotonic event sequences per event without leaking across events', function () {
    $primaryEvent = Event::factory()->active()->create();
    $secondaryEvent = Event::factory()->active()->create();

    $service = app(EventOperationsSequenceService::class);

    expect($service->nextSequenceForEvent($primaryEvent))->toBe(1)
        ->and($service->nextSequenceForEvent($secondaryEvent))->toBe(1);

    EventOperationEvent::factory()->create([
        'event_id' => $primaryEvent->id,
        'event_sequence' => 1,
    ]);

    EventOperationEvent::factory()->create([
        'event_id' => $secondaryEvent->id,
        'event_sequence' => 1,
    ]);

    EventOperationEvent::factory()->create([
        'event_id' => $primaryEvent->id,
        'event_sequence' => 2,
    ]);

    expect($service->nextSequenceForEvent($primaryEvent))->toBe(3)
        ->and($service->nextSequenceForEvent($secondaryEvent))->toBe(2)
        ->and($service->formatTimelineCursor(27))->toBe('evt_000027');
});

it('finds idempotent append candidates by event correlation station and event keys', function () {
    $event = Event::factory()->active()->create();

    $existing = EventOperationEvent::factory()->create([
        'event_id' => $event->id,
        'station_key' => 'gallery',
        'event_key' => 'media.published.gallery',
        'correlation_key' => 'corr_gallery_publish',
        'dedupe_window_key' => 'publish_window',
    ]);

    $service = app(EventOperationsSequenceService::class);

    expect($service->findIdempotentEvent(
        $event,
        stationKey: 'gallery',
        eventKey: 'media.published.gallery',
        correlationKey: 'corr_gallery_publish',
        dedupeWindowKey: 'publish_window',
    )?->is($existing))->toBeTrue()
        ->and($service->findIdempotentEvent(
            $event,
            stationKey: 'gallery',
            eventKey: 'media.published.wall',
            correlationKey: 'corr_gallery_publish',
            dedupeWindowKey: 'publish_window',
        ))->toBeNull();
});
