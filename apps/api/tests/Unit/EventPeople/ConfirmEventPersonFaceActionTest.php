<?php

use App\Modules\EventPeople\Actions\ConfirmEventPersonFaceAction;
use App\Modules\EventPeople\Enums\EventPersonAssignmentStatus;
use App\Modules\EventPeople\Models\EventPerson;
use App\Modules\EventPeople\Models\EventPersonFaceAssignment;
use App\Modules\EventPeople\Models\EventPersonReviewQueueItem;
use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Models\EventMediaFace;
use App\Modules\MediaProcessing\Models\EventMedia;
use Database\Factories\OrganizationFactory;
use Database\Factories\UserFactory;

it('creates a person inline with a unique slug when confirming a face', function () {
    $organization = OrganizationFactory::new()->create();
    $user = UserFactory::new()->create();

    $event = Event::factory()->create(['organization_id' => $organization->id]);
    $media = EventMedia::factory()->create(['event_id' => $event->id]);
    $face = EventMediaFace::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'searchable' => true,
    ]);

    EventPerson::factory()->create([
        'event_id' => $event->id,
        'display_name' => 'Elaine Teste',
        'slug' => 'elaine-teste',
    ]);

    $reviewItem = EventPersonReviewQueueItem::query()->create([
        'event_id' => $event->id,
        'queue_key' => 'unknown-face:' . $face->id,
        'type' => 'unknown_person',
        'status' => 'pending',
        'priority' => 100,
        'event_media_face_id' => $face->id,
        'payload' => ['question' => 'Quem e esta pessoa?'],
    ]);

    $result = app(ConfirmEventPersonFaceAction::class)->execute($event, $face, $user, [
        'person' => [
            'display_name' => 'Elaine Teste',
        ],
    ], $reviewItem);

    expect($result['person']->slug)->toBe('elaine-teste-2')
        ->and($result['review_item']->status->value)->toBe('resolved')
        ->and($result['person']->avatar_face_id)->toBe($face->id);
});

it('moves the confirmed assignment without duplicating rows for the same face', function () {
    $organization = OrganizationFactory::new()->create();
    $user = UserFactory::new()->create();

    $event = Event::factory()->create(['organization_id' => $organization->id]);
    $media = EventMedia::factory()->create(['event_id' => $event->id]);
    $face = EventMediaFace::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'searchable' => true,
    ]);
    $firstPerson = EventPerson::factory()->create(['event_id' => $event->id]);
    $secondPerson = EventPerson::factory()->create(['event_id' => $event->id]);
    $reviewItem = EventPersonReviewQueueItem::query()->create([
        'event_id' => $event->id,
        'queue_key' => 'unknown-face:' . $face->id,
        'type' => 'unknown_person',
        'status' => 'pending',
        'priority' => 100,
        'event_media_face_id' => $face->id,
        'payload' => ['question' => 'Quem e esta pessoa?'],
    ]);

    app(ConfirmEventPersonFaceAction::class)->execute($event, $face, $user, [
        'person_id' => $firstPerson->id,
    ], $reviewItem);

    app(ConfirmEventPersonFaceAction::class)->execute($event, $face->fresh(), $user, [
        'person_id' => $secondPerson->id,
    ], $reviewItem->fresh());

    expect(EventPersonFaceAssignment::query()
        ->where('event_media_face_id', $face->id)
        ->count())->toBe(1);

    $assignment = EventPersonFaceAssignment::query()
        ->where('event_media_face_id', $face->id)
        ->first();

    expect($assignment->event_person_id)->toBe($secondPerson->id)
        ->and($assignment->status)->toBe(EventPersonAssignmentStatus::Confirmed);
});
