<?php

namespace Database\Factories;

use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventMediaIntelligenceSettingFactory extends Factory
{
    protected $model = EventMediaIntelligenceSetting::class;

    public function definition(): array
    {
        return array_merge(
            EventMediaIntelligenceSetting::defaultAttributes(),
            [
                'event_id' => EventFactory::new(),
                'enabled' => true,
            ],
        );
    }

    public function disabled(): static
    {
        return $this->state(fn () => [
            'enabled' => false,
        ]);
    }

    public function gate(): static
    {
        return $this->state(fn () => [
            'mode' => 'gate',
            'fallback_mode' => 'review',
        ]);
    }
}
