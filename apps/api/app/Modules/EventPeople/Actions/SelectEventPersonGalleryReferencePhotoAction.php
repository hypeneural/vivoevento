<?php

namespace App\Modules\EventPeople\Actions;

use App\Modules\EventPeople\Enums\EventPersonAssignmentStatus;
use App\Modules\EventPeople\Enums\EventPersonReferencePhotoPurpose;
use App\Modules\EventPeople\Models\EventPerson;
use App\Modules\EventPeople\Models\EventPersonFaceAssignment;
use App\Modules\EventPeople\Models\EventPersonReferencePhoto;
use App\Modules\FaceSearch\Models\EventMediaFace;
use App\Modules\Users\Models\User;
use Illuminate\Validation\ValidationException;

class SelectEventPersonGalleryReferencePhotoAction
{
    public function __construct(
        private readonly UpsertEventPersonReferencePhotoAction $upsertReferencePhoto,
    ) {}

    public function execute(
        EventPerson $person,
        EventMediaFace $face,
        User $user,
        EventPersonReferencePhotoPurpose $purpose = EventPersonReferencePhotoPurpose::Matching,
    ): EventPersonReferencePhoto {
        if ((int) $person->event_id !== (int) $face->event_id) {
            throw ValidationException::withMessages([
                'event_media_face_id' => 'O rosto escolhido nao pertence ao mesmo evento da pessoa.',
            ]);
        }

        $confirmedAssignmentExists = EventPersonFaceAssignment::query()
            ->where('event_id', $person->event_id)
            ->where('event_person_id', $person->id)
            ->where('event_media_face_id', $face->id)
            ->where('status', EventPersonAssignmentStatus::Confirmed->value)
            ->exists();

        if (! $confirmedAssignmentExists) {
            throw ValidationException::withMessages([
                'event_media_face_id' => 'Escolha um rosto ja confirmado dessa pessoa no acervo do evento.',
            ]);
        }

        return $this->upsertReferencePhoto->execute(
            $person,
            $face,
            $user,
            $purpose,
            false,
        );
    }
}
