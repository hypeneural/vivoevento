<?php

namespace App\Modules\EventPeople\Actions;

use App\Modules\EventPeople\Enums\EventPersonAssignmentSource;
use App\Modules\EventPeople\Enums\EventPersonAssignmentStatus;
use App\Modules\EventPeople\Enums\EventPersonReferencePhotoStatus;
use App\Modules\EventPeople\Jobs\ProjectEventPeopleOperationalCountersJob;
use App\Modules\EventPeople\Jobs\ProjectEventPeopleReviewQueueJob;
use App\Modules\EventPeople\Jobs\SyncEventPersonRepresentativeFacesJob;
use App\Modules\EventPeople\Models\EventPersonFaceAssignment;
use App\Modules\EventPeople\Models\EventPersonReferencePhoto;
use App\Modules\EventPeople\Models\EventPersonReviewQueueItem;
use App\Modules\EventPeople\Support\EventPeopleStateMachine;
use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Models\EventMediaFace;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SplitEventPersonFaceAction
{
    public function __construct(
        private readonly ConfirmEventPersonFaceAction $confirmAction,
        private readonly ProjectEventPeopleReviewQueueAction $reviewQueueProjector,
        private readonly EventPeopleStateMachine $stateMachine,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{review_item: ?EventPersonReviewQueueItem, face: EventMediaFace, assignment: ?EventPersonFaceAssignment, person?: \App\Modules\EventPeople\Models\EventPerson}
     */
    public function execute(
        Event $event,
        EventMediaFace $face,
        User $user,
        array $payload,
        ?EventPersonReviewQueueItem $reviewItem = null,
    ): array {
        if ((int) $face->event_id !== (int) $event->id) {
            throw ValidationException::withMessages([
                'event_media_face_id' => 'O rosto informado nao pertence ao evento.',
            ]);
        }

        if (array_key_exists('person_id', $payload) || array_key_exists('person', $payload)) {
            return $this->confirmAction->execute($event, $face, $user, $payload, $reviewItem);
        }

        return DB::transaction(function () use ($event, $face, $user, $reviewItem): array {
            $assignment = EventPersonFaceAssignment::query()
                ->where('event_id', $event->id)
                ->where('event_media_face_id', $face->id)
                ->where('status', EventPersonAssignmentStatus::Confirmed->value)
                ->lockForUpdate()
                ->first();

            if ($assignment) {
                $this->stateMachine->transitionAssignment($assignment, EventPersonAssignmentStatus::Rejected, [
                    'source' => EventPersonAssignmentSource::ManualCorrected->value,
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now(),
                ]);

                $archivedPhotos = EventPersonReferencePhoto::query()
                    ->where('event_person_id', $assignment->event_person_id)
                    ->where('event_media_face_id', $face->id)
                    ->where('status', EventPersonReferencePhotoStatus::Active->value)
                    ->get();

                $archivedPhotos->each(fn (EventPersonReferencePhoto $photo) => $this->stateMachine->transitionReferencePhoto(
                        $photo,
                        EventPersonReferencePhotoStatus::Archived,
                        ['updated_by' => $user->id],
                    ));

                \App\Modules\EventPeople\Models\EventPerson::query()
                    ->where('id', $assignment->event_person_id)
                    ->update([
                        'avatar_face_id' => DB::raw("CASE WHEN avatar_face_id = {$face->id} THEN NULL ELSE avatar_face_id END"),
                        'avatar_media_id' => DB::raw("CASE WHEN avatar_face_id = {$face->id} THEN NULL ELSE avatar_media_id END"),
                        'primary_reference_photo_id' => $archivedPhotos->isEmpty()
                            ? DB::raw('primary_reference_photo_id')
                            : DB::raw('CASE WHEN primary_reference_photo_id IN (' . $archivedPhotos->pluck('id')->implode(',') . ') THEN NULL ELSE primary_reference_photo_id END'),
                        'updated_by' => $user->id,
                        'updated_at' => now(),
                    ]);
            }

            $projectedItem = $this->reviewQueueProjector->executeForFace(
                $face->fresh(['personAssignments.person']),
                reopenIgnored: true,
            );

            ProjectEventPeopleOperationalCountersJob::dispatch($event->id);
            ProjectEventPeopleReviewQueueJob::dispatch($event->id, $face->id);

            if ($assignment?->event_person_id) {
                SyncEventPersonRepresentativeFacesJob::dispatch($event->id, (int) $assignment->event_person_id);
            }

            return [
                'review_item' => $projectedItem?->fresh(['person', 'face']),
                'face' => $face->fresh(['personAssignments.person', 'reviewQueueItems']),
                'assignment' => $assignment?->fresh(),
            ];
        });
    }
}
