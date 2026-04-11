<?php

namespace App\Modules\EventPeople\Actions;

use App\Modules\EventPeople\Enums\EventPersonAssignmentSource;
use App\Modules\EventPeople\Enums\EventPersonAssignmentStatus;
use App\Modules\EventPeople\Jobs\ProjectEventPeopleOperationalCountersJob;
use App\Modules\EventPeople\Jobs\ProjectEventPeopleReviewQueueJob;
use App\Modules\EventPeople\Jobs\SyncEventPersonRepresentativeFacesJob;
use App\Modules\EventPeople\Models\EventPersonFaceAssignment;
use App\Modules\EventPeople\Models\EventPersonReviewQueueItem;
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
                $assignment->forceFill([
                    'status' => EventPersonAssignmentStatus::Rejected->value,
                    'source' => EventPersonAssignmentSource::ManualCorrected->value,
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now(),
                ])->save();
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
