<?php

use App\Modules\EventPeople\Enums\EventPersonReviewQueueStatus;
use App\Modules\EventPeople\Queries\ListEventPeopleQuery;
use App\Modules\EventPeople\Queries\ListEventPeopleReviewQueueQuery;
use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Models\EventMediaFace;
use App\Modules\MediaProcessing\Models\EventMedia;

it('freezes the people list predicate in a dedicated query object and exposes explain output', function () {
    $event = Event::factory()->create();

    \App\Modules\EventPeople\Models\EventPerson::factory()->create([
        'event_id' => $event->id,
        'display_name' => 'Ana',
        'status' => 'active',
    ]);

    $query = app(ListEventPeopleQuery::class);
    $page = $query->paginate($event, ['status' => 'active'], 10);
    $plan = $query->explain($event, ['status' => 'active']);
    $planText = strtoupper(json_encode($plan));

    expect($page->items())->toHaveCount(1)
        ->and($plan)->not->toBeEmpty()
        ->and($planText)->toContain('EVENT_PEOPLE');
});

it('freezes the review queue predicate in a dedicated query object and exposes explain output', function () {
    $event = Event::factory()->create();
    $media = EventMedia::factory()->create(['event_id' => $event->id]);
    $face = EventMediaFace::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
    ]);

    \App\Modules\EventPeople\Models\EventPersonReviewQueueItem::query()->create([
        'event_id' => $event->id,
        'queue_key' => 'unknown-face:' . $face->id,
        'type' => 'unknown_person',
        'status' => EventPersonReviewQueueStatus::Pending->value,
        'priority' => 100,
        'event_media_face_id' => $face->id,
        'payload' => ['question' => 'Quem e esta pessoa?'],
        'last_signal_at' => now(),
    ]);

    $query = app(ListEventPeopleReviewQueueQuery::class);
    $page = $query->paginate($event, ['status' => EventPersonReviewQueueStatus::Pending->value], 10);
    $plan = $query->explain($event, ['status' => EventPersonReviewQueueStatus::Pending->value]);
    $planText = strtoupper(json_encode($plan));

    expect($page->items())->toHaveCount(1)
        ->and($plan)->not->toBeEmpty()
        ->and($planText)->toContain('EVENT_PERSON_REVIEW_QUEUE');
});
