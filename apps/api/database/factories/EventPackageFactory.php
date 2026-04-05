<?php

namespace Database\Factories;

use App\Modules\Billing\Enums\EventPackageAudience;
use App\Modules\Billing\Models\EventPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventPackageFactory extends Factory
{
    protected $model = EventPackage::class;

    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'code' => fake()->slug(2),
            'name' => ucfirst($name),
            'description' => fake()->sentence(),
            'target_audience' => fake()->randomElement(EventPackageAudience::cases())->value,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 100),
        ];
    }
}
