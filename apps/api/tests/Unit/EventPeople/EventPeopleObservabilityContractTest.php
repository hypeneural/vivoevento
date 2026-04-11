<?php

use App\Modules\EventPeople\Enums\EventPersonAssignmentStatus;
use App\Modules\EventPeople\Enums\EventPersonRepresentativeSyncStatus;
use App\Modules\EventPeople\Enums\EventPersonReviewQueueStatus;
use App\Modules\EventPeople\Enums\EventPersonReviewQueueType;
use App\Modules\EventPeople\Enums\EventPersonStatus;
use App\Modules\EventPeople\Models\EventPerson;
use App\Modules\EventPeople\Models\EventPersonFaceAssignment;
use App\Modules\EventPeople\Models\EventPersonRepresentativeFace;
use App\Modules\EventPeople\Models\EventPersonReviewQueueItem;
use App\Modules\EventPeople\Services\EventPeopleOperationalMetricsService;
use App\Modules\FaceSearch\Models\EventMediaFace;
use App\Modules\MediaProcessing\Models\EventMedia;

it('returns the minimum operational metric snapshot scoped by event', function () {
    $event = \Database\Factories\EventFactory::new()->create();
    $otherEvent = \Database\Factories\EventFactory::new()->create();

    $activePerson = EventPerson::factory()->create([
        'event_id' => $event->id,
        'status' => EventPersonStatus::Active->value,
    ]);

    EventPerson::factory()->create([
        'event_id' => $event->id,
        'status' => EventPersonStatus::Draft->value,
    ]);

    EventPerson::factory()->create([
        'event_id' => $otherEvent->id,
        'status' => EventPersonStatus::Active->value,
    ]);

    $media = EventMedia::factory()->approved()->published()->create([
        'event_id' => $event->id,
    ]);

    $confirmedFace = EventMediaFace::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
    ]);

    EventPersonFaceAssignment::factory()->create([
        'event_id' => $event->id,
        'event_person_id' => $activePerson->id,
        'event_media_face_id' => $confirmedFace->id,
        'status' => EventPersonAssignmentStatus::Confirmed->value,
    ]);

    EventPersonReviewQueueItem::query()->create([
        'event_id' => $event->id,
        'queue_key' => 'unknown-face:' . $confirmedFace->id,
        'type' => EventPersonReviewQueueType::UnknownPerson->value,
        'status' => EventPersonReviewQueueStatus::Pending->value,
        'priority' => 100,
        'event_media_face_id' => $confirmedFace->id,
    ]);

    EventPersonReviewQueueItem::query()->create([
        'event_id' => $event->id,
        'queue_key' => 'conflict-face:' . $confirmedFace->id,
        'type' => EventPersonReviewQueueType::IdentityConflict->value,
        'status' => EventPersonReviewQueueStatus::Conflict->value,
        'priority' => 200,
        'event_media_face_id' => $confirmedFace->id,
    ]);

    foreach ([EventPersonRepresentativeSyncStatus::Pending, EventPersonRepresentativeSyncStatus::Failed] as $index => $status) {
        $face = EventMediaFace::factory()->create([
            'event_id' => $event->id,
            'event_media_id' => $media->id,
            'face_index' => $index + 1,
        ]);

        EventPersonRepresentativeFace::query()->create([
            'event_id' => $event->id,
            'event_person_id' => $activePerson->id,
            'event_media_face_id' => $face->id,
            'rank_score' => 1.0,
            'sync_status' => $status->value,
        ]);
    }

    expect(app(EventPeopleOperationalMetricsService::class)->snapshot($event->id))->toBe([
        'people_active' => 1,
        'people_draft' => 1,
        'assignments_confirmed' => 1,
        'review_queue_pending' => 1,
        'review_queue_conflict' => 1,
        'aws_sync_pending' => 1,
        'aws_sync_failed' => 1,
    ]);
});
