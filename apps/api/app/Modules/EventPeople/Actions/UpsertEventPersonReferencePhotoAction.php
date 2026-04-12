<?php

namespace App\Modules\EventPeople\Actions;

use App\Modules\EventPeople\Enums\EventPersonReferencePhotoPurpose;
use App\Modules\EventPeople\Enums\EventPersonReferencePhotoSource;
use App\Modules\EventPeople\Enums\EventPersonReferencePhotoStatus;
use App\Modules\EventPeople\Models\EventPerson;
use App\Modules\EventPeople\Models\EventPersonReferencePhoto;
use App\Modules\FaceSearch\Models\EventMediaFace;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;

class UpsertEventPersonReferencePhotoAction
{
    public function __construct(
        private readonly \App\Modules\EventPeople\Support\EventPeopleStateMachine $stateMachine,
    ) {}

    public function execute(
        EventPerson $person,
        EventMediaFace $face,
        User $user,
        EventPersonReferencePhotoPurpose $purpose,
        bool $isPrimaryAvatar = false,
    ): EventPersonReferencePhoto {
        return DB::transaction(function () use ($person, $face, $user, $purpose, $isPrimaryAvatar): EventPersonReferencePhoto {
            if ($isPrimaryAvatar) {
                EventPersonReferencePhoto::query()
                    ->where('event_person_id', $person->id)
                    ->where('is_primary_avatar', true)
                    ->where('status', EventPersonReferencePhotoStatus::Active->value)
                    ->where('event_media_face_id', '!=', $face->id)
                    ->get()
                    ->each(fn (EventPersonReferencePhoto $row) => $this->stateMachine->transitionReferencePhoto(
                        $row,
                        EventPersonReferencePhotoStatus::Archived,
                        ['updated_by' => $user->id],
                    ));
            }

            $row = EventPersonReferencePhoto::query()->firstOrNew([
                'event_id' => $person->event_id,
                'event_person_id' => $person->id,
                'event_media_face_id' => $face->id,
            ]);

            $existingStatus = $row->status instanceof EventPersonReferencePhotoStatus
                ? $row->status
                : ($row->exists ? EventPersonReferencePhotoStatus::from((string) $row->status) : null);

            $existingPurpose = $row->purpose instanceof EventPersonReferencePhotoPurpose
                ? $row->purpose
                : ($row->exists && is_string($row->purpose)
                    ? EventPersonReferencePhotoPurpose::from((string) $row->purpose)
                    : null);

            $row->fill([
                'source' => EventPersonReferencePhotoSource::EventFace->value,
                'event_media_id' => $face->event_media_id,
                'reference_upload_media_id' => null,
                'purpose' => $this->mergePurpose($existingPurpose, $purpose)->value,
                'status' => $row->exists ? ($row->status?->value ?? $row->status) : EventPersonReferencePhotoStatus::Active->value,
                'quality_score' => $face->quality_score,
                'is_primary_avatar' => $isPrimaryAvatar,
                'created_by' => $row->exists ? $row->created_by : $user->id,
                'updated_by' => $user->id,
            ])->save();

            if ($existingStatus && $existingStatus !== EventPersonReferencePhotoStatus::Active) {
                $this->stateMachine->transitionReferencePhoto($row, EventPersonReferencePhotoStatus::Active, [
                    'updated_by' => $user->id,
                ]);
            }

            return $row->fresh(['face']);
        });
    }

    private function mergePurpose(
        ?EventPersonReferencePhotoPurpose $existing,
        EventPersonReferencePhotoPurpose $incoming,
    ): EventPersonReferencePhotoPurpose {
        if ($existing === null || $existing === $incoming) {
            return $incoming;
        }

        if ($existing === EventPersonReferencePhotoPurpose::Both || $incoming === EventPersonReferencePhotoPurpose::Both) {
            return EventPersonReferencePhotoPurpose::Both;
        }

        return EventPersonReferencePhotoPurpose::Both;
    }
}
