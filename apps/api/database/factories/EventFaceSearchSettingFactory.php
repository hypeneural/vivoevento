<?php

namespace Database\Factories;

use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventFaceSearchSettingFactory extends Factory
{
    protected $model = EventFaceSearchSetting::class;

    public function definition(): array
    {
        return array_merge(EventFaceSearchSetting::defaultAttributes(), [
            'event_id' => EventFactory::new(),
        ]);
    }

    public function enabled(): static
    {
        return $this->state(fn () => [
            'enabled' => true,
        ]);
    }

    public function publicSelfieSearch(): static
    {
        return $this->state(fn () => [
            'enabled' => true,
            'allow_public_selfie_search' => true,
        ]);
    }
}
