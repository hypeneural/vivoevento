<?php

use App\Modules\EventPeople\Enums\EventPersonAssignmentStatus;
use App\Modules\EventPeople\Enums\EventPersonReferencePhotoStatus;
use App\Modules\EventPeople\Enums\EventPersonReviewQueueStatus;
use App\Modules\EventPeople\Enums\EventPersonStatus;
use App\Modules\EventPeople\Models\EventPerson;
use App\Modules\EventPeople\Models\EventPersonFaceAssignment;
use App\Modules\EventPeople\Models\EventPersonReferencePhoto;
use App\Modules\EventPeople\Models\EventPersonReviewQueueItem;
use App\Modules\EventPeople\Support\EventPeopleStateMachine;
use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Models\EventMediaFace;
use App\Modules\MediaProcessing\Models\EventMedia;

it('reopens a resolved review item back to pending with transition metadata', function () {
    $event = Event::factory()->create();
    $person = EventPerson::factory()->create(['event_id' => $event->id]);
    $media = EventMedia::factory()->create(['event_id' => $event->id]);
    $face = EventMediaFace::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
    ]);

    $item = EventPersonReviewQueueItem::query()->create([
        'event_id' => $event->id,
        'queue_key' => 'unknown-face:' . $face->id,
        'type' => 'unknown_person',
        'status' => 'resolved',
        'priority' => 100,
        'event_person_id' => $person->id,
        'event_media_face_id' => $face->id,
        'payload' => ['resolution' => 'confirmed'],
        'resolved_at' => now(),
    ]);

    app(EventPeopleStateMachine::class)->transitionReviewItem($item, EventPersonReviewQueueStatus::Pending, [
        'reason' => 'manual_split',
    ]);

    $item->refresh();

    expect($item->status)->toBe(EventPersonReviewQueueStatus::Pending)
        ->and($item->resolved_at)->toBeNull()
        ->and($item->payload['state_transition']['from'])->toBe('resolved')
        ->and($item->payload['state_transition']['to'])->toBe('pending')
        ->and($item->payload['state_transition']['reason'])->toBe('manual_split');
});

it('allows explicit person lifecycle transitions used by the operational cockpit', function () {
    $person = EventPerson::factory()->create([
        'status' => EventPersonStatus::Active->value,
    ]);

    $machine = app(EventPeopleStateMachine::class);

    $machine->transitionPerson($person, EventPersonStatus::Draft);
    $machine->transitionPerson($person->fresh(), EventPersonStatus::Hidden);

    expect($person->fresh()->status)->toBe(EventPersonStatus::Hidden);
});

it('rejects invalid reference photo transitions', function () {
    $photo = EventPersonReferencePhoto::factory()->create([
        'status' => EventPersonReferencePhotoStatus::Invalid->value,
    ]);

    $machine = app(EventPeopleStateMachine::class);

    expect(fn () => $machine->transitionReferencePhoto($photo, EventPersonReferencePhotoStatus::Active))
        ->toThrow(\DomainException::class);
});

it('moves confirmed assignments to rejected through the explicit assignment state machine', function () {
    $event = Event::factory()->create();
    $person = EventPerson::factory()->create(['event_id' => $event->id]);
    $media = EventMedia::factory()->create(['event_id' => $event->id]);
    $face = EventMediaFace::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
    ]);

    $assignment = EventPersonFaceAssignment::factory()->create([
        'event_id' => $event->id,
        'event_person_id' => $person->id,
        'event_media_face_id' => $face->id,
        'status' => EventPersonAssignmentStatus::Confirmed->value,
    ]);

    app(EventPeopleStateMachine::class)->transitionAssignment($assignment, EventPersonAssignmentStatus::Rejected, [
        'source' => 'manual_corrected',
    ]);

    expect($assignment->fresh()->status)->toBe(EventPersonAssignmentStatus::Rejected);
});
