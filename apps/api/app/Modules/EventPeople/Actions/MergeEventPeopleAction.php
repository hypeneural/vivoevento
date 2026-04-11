<?php

namespace App\Modules\EventPeople\Actions;

use App\Modules\EventPeople\Enums\EventPersonAssignmentSource;
use App\Modules\EventPeople\Enums\EventPersonAssignmentStatus;
use App\Modules\EventPeople\Enums\EventPersonReviewQueueStatus;
use App\Modules\EventPeople\Enums\EventPersonStatus;
use App\Modules\EventPeople\Jobs\ProjectEventPeopleOperationalCountersJob;
use App\Modules\EventPeople\Jobs\ProjectEventPeopleReviewQueueJob;
use App\Modules\EventPeople\Jobs\SyncEventPersonRepresentativeFacesJob;
use App\Modules\EventPeople\Models\EventPerson;
use App\Modules\EventPeople\Models\EventPersonFaceAssignment;
use App\Modules\EventPeople\Models\EventPersonReviewQueueItem;
use App\Modules\Events\Models\Event;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MergeEventPeopleAction
{
    public function __construct(
        private readonly ProjectEventPeopleReviewQueueAction $reviewQueueProjector,
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

                if (($assignment->status?->value ?? $assignment->status) === EventPersonAssignmentStatus::Confirmed->value) {
                    $assignment->forceFill([
                        'event_person_id' => $targetPerson->id,
                        'source' => EventPersonAssignmentSource::ManualCorrected->value,
                        'reviewed_by' => $user->id,
                        'reviewed_at' => now(),
                    ])->save();

                    continue;
                }

                $assignment->forceFill([
                    'event_person_id' => $targetPerson->id,
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now(),
                ])->save();
            }

            $sourcePerson->forceFill([
                'status' => EventPersonStatus::Hidden->value,
                'updated_by' => $user->id,
            ])->save();

            if (! $targetPerson->avatar_face_id && $sourcePerson->avatar_face_id) {
                $targetPerson->forceFill([
                    'avatar_face_id' => $sourcePerson->avatar_face_id,
                    'avatar_media_id' => $sourcePerson->avatar_media_id,
                    'updated_by' => $user->id,
                ])->save();
            }

            if ($reviewItem) {
                $reviewItem->forceFill([
                    'status' => EventPersonReviewQueueStatus::Resolved->value,
                    'event_person_id' => $targetPerson->id,
                    'resolved_at' => now(),
                    'resolved_by' => $user->id,
                    'payload' => array_merge($reviewItem->payload ?? [], [
                        'resolution' => 'merged',
                        'source_person_id' => $sourcePerson->id,
                        'target_person_id' => $targetPerson->id,
                    ]),
                ])->save();
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

            return [
                'source_person' => $sourcePerson->fresh(),
                'target_person' => $targetPerson->fresh(),
                'review_item' => $reviewItem?->fresh(['person', 'face']),
            ];
        });
    }
}
