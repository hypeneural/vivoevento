<?php

namespace App\Modules\EventPeople\Actions;

use App\Modules\EventPeople\Enums\EventPersonAssignmentSource;
use App\Modules\EventPeople\Enums\EventPersonAssignmentStatus;
use App\Modules\EventPeople\Enums\EventPersonReferencePhotoPurpose;
use App\Modules\EventPeople\Enums\EventPersonReferencePhotoStatus;
use App\Modules\EventPeople\Enums\EventPersonReviewQueueStatus;
use App\Modules\EventPeople\Enums\EventPersonStatus;
use App\Modules\EventPeople\Jobs\ProjectEventPeopleOperationalCountersJob;
use App\Modules\EventPeople\Jobs\ProjectEventPeopleReviewQueueJob;
use App\Modules\EventPeople\Jobs\SyncEventPersonRepresentativeFacesJob;
use App\Modules\EventPeople\Models\EventPerson;
use App\Modules\EventPeople\Models\EventPersonFaceAssignment;
use App\Modules\EventPeople\Models\EventPersonReferencePhoto;
use App\Modules\EventPeople\Models\EventPersonReviewQueueItem;
use App\Modules\EventPeople\Support\EventPeopleStateMachine;
use App\Modules\Events\Models\Event;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MergeEventPeopleAction
{
    public function __construct(
        private readonly ProjectEventPeopleReviewQueueAction $reviewQueueProjector,
        private readonly EventPeopleStateMachine $stateMachine,
    ) {}

    /**
     * @return array{source_person: EventPerson, target_person: EventPerson, review_item: ?EventPersonReviewQueueItem}
     */
    public function execute(
        Event $event,
        EventPerson $sourcePerson,
        EventPerson $targetPerson,
        User $user,
        ?EventPersonReviewQueueItem $reviewItem = null,
    ): array {
        if ((int) $sourcePerson->event_id !== (int) $event->id || (int) $targetPerson->event_id !== (int) $event->id) {
            throw ValidationException::withMessages([
                'person' => 'As pessoas informadas precisam pertencer ao evento.',
            ]);
        }

        if ((int) $sourcePerson->id === (int) $targetPerson->id) {
            throw ValidationException::withMessages([
                'target_person_id' => 'A pessoa de destino precisa ser diferente da origem.',
            ]);
        }

        return DB::transaction(function () use ($event, $sourcePerson, $targetPerson, $user, $reviewItem): array {
            $assignments = EventPersonFaceAssignment::query()
                ->where('event_id', $event->id)
                ->where('event_person_id', $sourcePerson->id)
                ->lockForUpdate()
                ->get();

            $affectedFaceIds = [];

            foreach ($assignments as $assignment) {
                $affectedFaceIds[] = (int) $assignment->event_media_face_id;

                $assignment->forceFill([
                    'event_person_id' => $targetPerson->id,
                ])->save();

                if (($assignment->status?->value ?? $assignment->status) === EventPersonAssignmentStatus::Confirmed->value) {
                    $this->stateMachine->transitionAssignment($assignment, EventPersonAssignmentStatus::Confirmed, [
                        'source' => EventPersonAssignmentSource::ManualCorrected->value,
                        'reviewed_by' => $user->id,
                        'reviewed_at' => now(),
                    ]);

                    continue;
                }

                $this->stateMachine->transitionAssignment(
                    $assignment,
                    $assignment->status instanceof EventPersonAssignmentStatus
                        ? $assignment->status
                        : EventPersonAssignmentStatus::from((string) $assignment->status),
                    [
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now(),
                    ],
                );
            }

            $this->moveReferencePhotos($sourcePerson, $targetPerson, $user);

            $this->stateMachine->transitionPerson($sourcePerson, EventPersonStatus::Hidden, [
                'updated_by' => $user->id,
            ]);

            if (! $targetPerson->avatar_face_id && $sourcePerson->avatar_face_id) {
                $targetPerson->forceFill([
                    'avatar_face_id' => $sourcePerson->avatar_face_id,
                    'avatar_media_id' => $sourcePerson->avatar_media_id,
                    'updated_by' => $user->id,
                ])->save();
            }

            if (! $targetPerson->primary_reference_photo_id && $sourcePerson->primary_reference_photo_id) {
                $primaryPhoto = EventPersonReferencePhoto::query()->find($sourcePerson->primary_reference_photo_id);

                if ($primaryPhoto && (int) $primaryPhoto->event_person_id === (int) $targetPerson->id && ($primaryPhoto->status?->value ?? $primaryPhoto->status) === EventPersonReferencePhotoStatus::Active->value) {
                    $targetPerson->forceFill([
                        'primary_reference_photo_id' => $primaryPhoto->id,
                        'updated_by' => $user->id,
                    ])->save();
                }
            }

            if ($sourcePerson->primary_reference_photo_id) {
                $sourcePerson->forceFill([
                    'primary_reference_photo_id' => null,
                    'updated_by' => $user->id,
                ])->save();
            }

            if ($reviewItem) {
                $reviewItem->forceFill([
                    'event_person_id' => $targetPerson->id,
                    'payload' => array_merge($reviewItem->payload ?? [], [
                        'resolution' => 'merged',
                        'source_person_id' => $sourcePerson->id,
                        'target_person_id' => $targetPerson->id,
                    ]),
                ])->save();

                $this->stateMachine->transitionReviewItem($reviewItem, EventPersonReviewQueueStatus::Resolved, [
                    'reason' => 'merged_into_target_person',
                    'resolved_by' => $user->id,
                    'resolved_at' => now(),
                ]);
            }

            foreach (array_unique($affectedFaceIds) as $faceId) {
                $face = \App\Modules\FaceSearch\Models\EventMediaFace::query()
                    ->with('personAssignments.person')
                    ->find($faceId);

                if ($face) {
                    $this->reviewQueueProjector->executeForFace($face, reopenIgnored: true);
                    ProjectEventPeopleReviewQueueJob::dispatch($event->id, $faceId);
                }
            }

            ProjectEventPeopleOperationalCountersJob::dispatch($event->id);
            SyncEventPersonRepresentativeFacesJob::dispatch($event->id, $targetPerson->id);
            SyncEventPersonRepresentativeFacesJob::dispatch($event->id, $sourcePerson->id);

            return [
                'source_person' => $sourcePerson->fresh(),
                'target_person' => $targetPerson->fresh(),
                'review_item' => $reviewItem?->fresh(['person', 'face']),
            ];
        });
    }

    private function moveReferencePhotos(EventPerson $sourcePerson, EventPerson $targetPerson, User $user): void
    {
        EventPersonReferencePhoto::query()
            ->where('event_person_id', $sourcePerson->id)
            ->get()
            ->each(function (EventPersonReferencePhoto $photo) use ($targetPerson, $user): void {
                $duplicate = EventPersonReferencePhoto::query()
                    ->where('event_person_id', $targetPerson->id)
                    ->when(
                        $photo->event_media_face_id !== null,
                        fn ($query) => $query->where('event_media_face_id', $photo->event_media_face_id),
                        fn ($query) => $query->where('reference_upload_media_id', $photo->reference_upload_media_id),
                    )
                    ->first();

                if ($duplicate) {
                    $duplicate->forceFill([
                        'purpose' => $this->mergePurpose(
                            $duplicate->purpose instanceof EventPersonReferencePhotoPurpose
                                ? $duplicate->purpose
                                : EventPersonReferencePhotoPurpose::from((string) $duplicate->purpose),
                            $photo->purpose instanceof EventPersonReferencePhotoPurpose
                                ? $photo->purpose
                                : EventPersonReferencePhotoPurpose::from((string) $photo->purpose),
                        )->value,
                        'updated_by' => $user->id,
                    ])->save();

                    $this->stateMachine->transitionReferencePhoto($photo, EventPersonReferencePhotoStatus::Archived, [
                        'updated_by' => $user->id,
                    ]);

                    return;
                }

                $photo->forceFill([
                    'event_person_id' => $targetPerson->id,
                    'updated_by' => $user->id,
                ])->save();
            });
    }

    private function mergePurpose(
        EventPersonReferencePhotoPurpose $left,
        EventPersonReferencePhotoPurpose $right,
    ): EventPersonReferencePhotoPurpose {
        if ($left === EventPersonReferencePhotoPurpose::Both || $right === EventPersonReferencePhotoPurpose::Both) {
            return EventPersonReferencePhotoPurpose::Both;
        }

        if ($left === $right) {
            return $left;
        }

        return EventPersonReferencePhotoPurpose::Both;
    }
}
