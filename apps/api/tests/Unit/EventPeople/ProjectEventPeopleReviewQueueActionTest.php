<?php

use App\Modules\EventPeople\Actions\ProjectEventPeopleReviewQueueAction;
use App\Modules\EventPeople\Enums\EventPersonAssignmentStatus;
use App\Modules\EventPeople\Enums\EventPersonReviewQueueStatus;
use App\Modules\EventPeople\Enums\EventPersonReviewQueueType;
use App\Modules\EventPeople\Models\EventPerson;
use App\Modules\EventPeople\Models\EventPersonFaceAssignment;
use App\Modules\EventPeople\Models\EventPersonReviewQueueItem;
use App\Modules\FaceSearch\Models\EventMediaFace;
use App\Modules\MediaProcessing\Models\EventMedia;

it('projects a searchable unassigned face as a pending unknown-person review item', function () {
    $event = \Database\Factories\EventFactory::new()->create();
    $media = EventMedia::factory()->create(['event_id' => $event->id]);
    $face = EventMediaFace::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'quality_tier' => 'search_priority',
        'quality_score' => 0.95,
        'searchable' => true,
    ]);

    $item = app(ProjectEventPeopleReviewQueueAction::class)->executeForFace($face);

    expect($item)->not->toBeNull()
        ->and($item->queue_key)->toBe('unknown-face:' . $face->id)
        ->and($item->status)->toBe(EventPersonReviewQueueStatus::Pending)
        ->and($item->priority)->toBeGreaterThan(100);
});

it('resolves the queue item when the face already has a confirmed person assignment', function () {
    $event = \Database\Factories\EventFactory::new()->create();
    $media = EventMedia::factory()->create(['event_id' => $event->id]);
    $face = EventMediaFace::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'searchable' => true,
    ]);
    $person = EventPerson::factory()->create(['event_id' => $event->id]);

    EventPersonFaceAssignment::query()->create([
        'event_id' => $event->id,
        'event_person_id' => $person->id,
        'event_media_face_id' => $face->id,
        'source' => 'manual_confirmed',
        'status' => EventPersonAssignmentStatus::Confirmed->value,
        'reviewed_at' => now(),
    ]);

    $item = app(ProjectEventPeopleReviewQueueAction::class)->executeForFace($face->fresh(['personAssignments.person']));

    expect($item)->not->toBeNull()
        ->and($item->status)->toBe(EventPersonReviewQueueStatus::Resolved)
        ->and($item->event_person_id)->toBe($person->id);
});

it('preserves ignored items until an explicit reopen is requested', function () {
    $event = \Database\Factories\EventFactory::new()->create();
    $media = EventMedia::factory()->create(['event_id' => $event->id]);
    $face = EventMediaFace::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'searchable' => true,
    ]);

    $item = EventPersonReviewQueueItem::query()->create([
        'event_id' => $event->id,
        'queue_key' => 'unknown-face:' . $face->id,
        'type' => 'unknown_person',
        'status' => EventPersonReviewQueueStatus::Ignored->value,
        'priority' => 50,
        'event_media_face_id' => $face->id,
        'payload' => ['resolution' => 'ignored'],
        'resolved_at' => now(),
    ]);

    $keptIgnored = app(ProjectEventPeopleReviewQueueAction::class)->executeForFace($face);
    $reopened = app(ProjectEventPeopleReviewQueueAction::class)->executeForFace($face, reopenIgnored: true);

    expect($keptIgnored->status)->toBe(EventPersonReviewQueueStatus::Ignored)
        ->and($reopened->status)->toBe(EventPersonReviewQueueStatus::Pending);
});

it('projects an identity conflict automatically when the same face points to multiple local identities', function () {
    $event = \Database\Factories\EventFactory::new()->create();
    $media = EventMedia::factory()->create(['event_id' => $event->id]);
    $face = EventMediaFace::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'searchable' => true,
    ]);
    $firstPerson = EventPerson::factory()->create([
        'event_id' => $event->id,
        'display_name' => 'Primeira Pessoa',
    ]);
    $secondPerson = EventPerson::factory()->create([
        'event_id' => $event->id,
        'display_name' => 'Segunda Pessoa',
    ]);

    EventPersonFaceAssignment::query()->create([
        'event_id' => $event->id,
        'event_person_id' => $firstPerson->id,
        'event_media_face_id' => $face->id,
        'source' => 'manual_corrected',
        'status' => EventPersonAssignmentStatus::Rejected->value,
        'reviewed_at' => now(),
    ]);

    EventPersonFaceAssignment::query()->create([
        'event_id' => $event->id,
        'event_person_id' => $secondPerson->id,
        'event_media_face_id' => $face->id,
        'source' => 'manual_confirmed',
        'status' => EventPersonAssignmentStatus::Confirmed->value,
        'reviewed_at' => now(),
    ]);

    $item = app(ProjectEventPeopleReviewQueueAction::class)->executeForFace($face->fresh(['personAssignments.person']));

    expect($item)->not->toBeNull()
        ->and($item->queue_key)->toBe('identity-conflict:' . $face->id)
        ->and($item->type)->toBe(EventPersonReviewQueueType::IdentityConflict)
        ->and($item->status)->toBe(EventPersonReviewQueueStatus::Conflict)
        ->and($item->payload['candidate_people'])->toHaveCount(2)
        ->and(collect($item->payload['candidate_people'])->pluck('display_name')->all())->toBe([
            'Segunda Pessoa',
            'Primeira Pessoa',
        ]);
});
