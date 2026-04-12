<?php

namespace App\Modules\EventPeople\Actions;

use App\Modules\EventPeople\Enums\EventPersonAssignmentSource;
use App\Modules\EventPeople\Enums\EventPersonAssignmentStatus;
use App\Modules\EventPeople\Enums\EventPersonReferencePhotoPurpose;
use App\Modules\EventPeople\Enums\EventPersonReviewQueueStatus;
use App\Modules\EventPeople\Enums\EventPersonSide;
use App\Modules\EventPeople\Enums\EventPersonStatus;
use App\Modules\EventPeople\Enums\EventPersonType;
use App\Modules\EventPeople\Jobs\ProjectEventPeopleOperationalCountersJob;
use App\Modules\EventPeople\Jobs\ProjectEventPeopleReviewQueueJob;
use App\Modules\EventPeople\Jobs\SyncEventPersonRepresentativeFacesJob;
use App\Modules\EventPeople\Models\EventPerson;
use App\Modules\EventPeople\Models\EventPersonFaceAssignment;
use App\Modules\EventPeople\Models\EventPersonReferencePhoto;
use App\Modules\EventPeople\Models\EventPersonReviewQueueItem;
use App\Modules\EventPeople\Support\EventPeopleStateMachine;
use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Models\EventMediaFace;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConfirmEventPersonFaceAction
{
    public function __construct(
        private readonly ProjectEventPeopleReviewQueueAction $reviewQueueProjector,
        private readonly UpsertEventPersonReferencePhotoAction $upsertReferencePhoto,
        private readonly EventPeopleStateMachine $stateMachine,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{person: EventPerson, assignment: EventPersonFaceAssignment, review_item: EventPersonReviewQueueItem, face: EventMediaFace}
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

        return DB::transaction(function () use ($event, $face, $user, $payload, $reviewItem): array {
            $previousPersonId = null;
            $person = $this->resolveTargetPerson($event, $user, $payload);

            $confirmedAssignment = EventPersonFaceAssignment::query()
                ->where('event_id', $event->id)
                ->where('event_media_face_id', $face->id)
                ->where('status', EventPersonAssignmentStatus::Confirmed->value)
                ->lockForUpdate()
                ->first();

            $previousPersonId = $confirmedAssignment?->event_person_id;

            $source = $confirmedAssignment && (int) $confirmedAssignment->event_person_id !== (int) $person->id
                ? EventPersonAssignmentSource::ManualCorrected
                : EventPersonAssignmentSource::ManualConfirmed;

            if ($confirmedAssignment) {
                $assignment = $confirmedAssignment;
                $assignment->forceFill([
                    'event_person_id' => $person->id,
                    'confidence' => 1,
                ])->save();

                $this->stateMachine->transitionAssignment($assignment, EventPersonAssignmentStatus::Confirmed, [
                    'source' => $source->value,
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now(),
                ]);
            } else {
                $existingForTarget = EventPersonFaceAssignment::query()
                    ->where('event_id', $event->id)
                    ->where('event_media_face_id', $face->id)
                    ->where('event_person_id', $person->id)
                    ->lockForUpdate()
                    ->first();

                $assignment = $existingForTarget ?? new EventPersonFaceAssignment();
                $assignment->fill([
                    'event_id' => $event->id,
                    'event_person_id' => $person->id,
                    'event_media_face_id' => $face->id,
                    'confidence' => 1,
                    'status' => $existingForTarget?->status?->value
                        ?? $existingForTarget?->status
                        ?? EventPersonAssignmentStatus::Suggested->value,
                ]);
                $assignment->save();

                $this->stateMachine->transitionAssignment($assignment, EventPersonAssignmentStatus::Confirmed, [
                    'source' => $source->value,
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now(),
                ]);
            }

            EventPersonFaceAssignment::query()
                ->where('event_id', $event->id)
                ->where('event_media_face_id', $face->id)
                ->where('id', '!=', $assignment->id)
                ->where('status', EventPersonAssignmentStatus::Confirmed->value)
                ->lockForUpdate()
                ->get()
                ->each(function (EventPersonFaceAssignment $otherAssignment) use ($face, $user): void {
                    $this->stateMachine->transitionAssignment($otherAssignment, EventPersonAssignmentStatus::Rejected, [
                        'source' => EventPersonAssignmentSource::ManualCorrected->value,
                        'reviewed_by' => $user->id,
                        'reviewed_at' => now(),
                    ]);

                    $this->archiveReferencePhotosForFace(
                        (int) $otherAssignment->event_person_id,
                        (int) $face->id,
                        $user,
                    );
                });

            $isPrimaryAvatar = ! $person->avatar_face_id || (int) $person->avatar_face_id === (int) $face->id;

            if ($isPrimaryAvatar) {
                $person->forceFill([
                    'avatar_face_id' => $face->id,
                    'avatar_media_id' => $face->event_media_id,
                    'updated_by' => $user->id,
                ])->save();
            }

            if ($previousPersonId !== null && (int) $previousPersonId !== (int) $person->id) {
                $this->archiveReferencePhotosForFace($previousPersonId, (int) $face->id, $user);
            }

            $this->upsertReferencePhoto->execute(
                $person->fresh(),
                $face,
                $user,
                $isPrimaryAvatar ? EventPersonReferencePhotoPurpose::Both : EventPersonReferencePhotoPurpose::Matching,
                $isPrimaryAvatar,
            );

            if ($reviewItem) {
                $reviewItem->forceFill([
                    'event_person_id' => $person->id,
                    'payload' => array_merge($reviewItem->payload ?? [], [
                        'resolution' => 'confirmed',
                        'event_person_id' => $person->id,
                    ]),
                ])->save();

                $this->stateMachine->transitionReviewItem($reviewItem, EventPersonReviewQueueStatus::Resolved, [
                    'reason' => 'manual_confirmation',
                    'resolved_by' => $user->id,
                    'resolved_at' => now(),
                ]);
            }

            $projectedItem = $this->reviewQueueProjector->executeForFace(
                $face->fresh(['personAssignments.person']),
                reopenIgnored: true,
            );

            ProjectEventPeopleOperationalCountersJob::dispatch($event->id);
            ProjectEventPeopleReviewQueueJob::dispatch($event->id, $face->id);
            SyncEventPersonRepresentativeFacesJob::dispatch($event->id, $person->id);

            if ($previousPersonId !== null && (int) $previousPersonId !== (int) $person->id) {
                SyncEventPersonRepresentativeFacesJob::dispatch($event->id, (int) $previousPersonId);
            }

            return [
                'person' => $person->fresh([
                    'mediaStats',
                    'primaryReferencePhoto.face',
                    'primaryReferencePhoto.uploadMedia',
                    'referencePhotos.face',
                    'referencePhotos.uploadMedia',
                    'representativeFaces.face',
                    'outgoingRelations.personA',
                    'outgoingRelations.personB',
                    'incomingRelations.personA',
                    'incomingRelations.personB',
                ]),
                'assignment' => $assignment->fresh(['person', 'face.media']),
                'review_item' => $projectedItem?->fresh(['person', 'face']),
                'face' => $face->fresh(['personAssignments.person', 'reviewQueueItems']),
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveTargetPerson(Event $event, User $user, array $payload): EventPerson
    {
        $personId = $payload['person_id'] ?? null;

        if (is_numeric($personId)) {
            $person = EventPerson::query()
                ->where('event_id', $event->id)
                ->find((int) $personId);

            if (! $person) {
                throw ValidationException::withMessages([
                    'person_id' => 'A pessoa selecionada nao pertence ao evento.',
                ]);
            }

            return $person;
        }

        $personData = $payload['person'] ?? null;

        if (! is_array($personData) || ! is_string($personData['display_name'] ?? null)) {
            throw ValidationException::withMessages([
                'person' => 'Informe uma pessoa existente ou os dados minimos da nova pessoa.',
            ]);
        }

        $displayName = trim((string) $personData['display_name']);

        if ($displayName === '') {
            throw ValidationException::withMessages([
                'person.display_name' => 'Informe o nome da pessoa.',
            ]);
        }

        $type = EventPersonType::tryFrom((string) ($personData['type'] ?? EventPersonType::Guest->value)) ?? EventPersonType::Guest;
        $side = EventPersonSide::tryFrom((string) ($personData['side'] ?? EventPersonSide::Neutral->value)) ?? EventPersonSide::Neutral;

        return EventPerson::query()->create([
            'event_id' => $event->id,
            'display_name' => $displayName,
            'slug' => $this->uniqueSlug($event->id, $displayName),
            'type' => $type->value,
            'side' => $side->value,
            'importance_rank' => (int) ($personData['importance_rank'] ?? 0),
            'notes' => $personData['notes'] ?? null,
            'status' => EventPersonStatus::Active->value,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    private function uniqueSlug(int $eventId, string $displayName): string
    {
        $base = Str::slug($displayName);
        $slug = $base !== '' ? $base : 'pessoa';
        $candidate = $slug;
        $suffix = 2;

        while (EventPerson::query()->where('event_id', $eventId)->where('slug', $candidate)->exists()) {
            $candidate = "{$slug}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }

    private function archiveReferencePhotosForFace(int $personId, int $faceId, User $user): void
    {
        $photos = EventPersonReferencePhoto::query()
            ->where('event_person_id', $personId)
            ->where('event_media_face_id', $faceId)
            ->where('status', 'active')
            ->get();

        $photos->each(fn (EventPersonReferencePhoto $photo) => $this->stateMachine->transitionReferencePhoto(
            $photo,
            \App\Modules\EventPeople\Enums\EventPersonReferencePhotoStatus::Archived,
            ['updated_by' => $user->id],
        ));

        if ($photos->isNotEmpty()) {
            EventPerson::query()
                ->where('id', $personId)
                ->whereIn('primary_reference_photo_id', $photos->pluck('id')->all())
                ->update([
                    'primary_reference_photo_id' => null,
                    'updated_by' => $user->id,
                    'updated_at' => now(),
                ]);
        }
    }
}
