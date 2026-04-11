<?php

namespace Database\Factories;

use App\Modules\EventPeople\Enums\EventPersonAssignmentSource;
use App\Modules\EventPeople\Enums\EventPersonAssignmentStatus;
use App\Modules\EventPeople\Models\EventPersonFaceAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventPersonFaceAssignmentFactory extends Factory
{
    protected $model = EventPersonFaceAssignment::class;

    public function definition(): array
    {
        $person = EventPersonFactory::new()->create();
        $face = EventMediaFaceFactory::new()->create([
            'event_id' => $person->event_id,
        ]);

        return [
            'event_id' => $person->event_id,
            'event_person_id' => $person->id,
            'event_media_face_id' => $face->id,
            'source' => EventPersonAssignmentSource::ManualConfirmed->value,
            'confidence' => 1.0,
            'status' => EventPersonAssignmentStatus::Confirmed->value,
            'reviewed_at' => now(),
        ];
    }
}
