<?php

namespace Database\Factories;

use App\Modules\Billing\Models\EventPackageFeature;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventPackageFeatureFactory extends Factory
{
    protected $model = EventPackageFeature::class;

    public function definition(): array
    {
        return [
            'event_package_id' => EventPackageFactory::new(),
            'feature_key' => fake()->randomElement([
                'hub.enabled',
                'wall.enabled',
                'play.enabled',
                'media.retention_days',
                'media.max_photos',
            ]),
            'feature_value' => fake()->randomElement(['true', 'false', '30', '90', '300']),
        ];
    }
}
