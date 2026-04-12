<?php

namespace App\Modules\EventPeople\Actions;

use App\Modules\EventPeople\Enums\EventPersonReferencePhotoStatus;
use App\Modules\EventPeople\Models\EventPerson;
use App\Modules\EventPeople\Models\EventPersonReferencePhoto;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetEventPersonPrimaryPhotoAction
{
    public function execute(
        EventPerson $person,
        EventPersonReferencePhoto $referencePhoto,
        User $user,
    ): EventPerson {
        if ((int) $person->id !== (int) $referencePhoto->event_person_id || (int) $person->event_id !== (int) $referencePhoto->event_id) {
            throw ValidationException::withMessages([
                'reference_photo_id' => 'A referencia escolhida nao pertence a essa pessoa.',
            ]);
        }

        if (($referencePhoto->status?->value ?? $referencePhoto->status) !== EventPersonReferencePhotoStatus::Active->value) {
            throw ValidationException::withMessages([
                'reference_photo_id' => 'A foto principal precisa ser escolhida entre referencias ativas.',
            ]);
        }

        return DB::transaction(function () use ($person, $referencePhoto, $user): EventPerson {
            $person->forceFill([
                'primary_reference_photo_id' => $referencePhoto->id,
                'updated_by' => $user->id,
            ])->save();

            return $person->fresh();
        });
    }
}
