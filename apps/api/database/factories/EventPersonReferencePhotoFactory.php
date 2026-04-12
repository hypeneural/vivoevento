<?php

namespace Database\Factories;

use App\Modules\EventPeople\Enums\EventPersonReferencePhotoPurpose;
use App\Modules\EventPeople\Enums\EventPersonReferencePhotoSource;
use App\Modules\EventPeople\Enums\EventPersonReferencePhotoStatus;
use App\Modules\EventPeople\Models\EventPersonReferencePhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventPersonReferencePhotoFactory extends Factory
{
    protected $model = EventPersonReferencePhoto::class;

    public function definition(): array
    {
        $person = EventPersonFactory::new()->create();
        $media = EventMediaFactory::new()->create([
            'event_id' => $person->event_id,
        ]);
        $face = EventMediaFaceFactory::new()->create([
            'event_id' => $person->event_id,
            'event_media_id' => $media->id,
        ]);

        return [
            'event_id' => $person->event_id,
            'event_person_id' => $person->id,
            'source' => EventPersonReferencePhotoSource::EventFace->value,
            'event_media_id' => $media->id,
            'event_media_face_id' => $face->id,
            'reference_upload_media_id' => null,
            'purpose' => EventPersonReferencePhotoPurpose::Matching->value,
            'status' => EventPersonReferencePhotoStatus::Active->value,
            'quality_score' => 0.9,
            'is_primary_avatar' => false,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
