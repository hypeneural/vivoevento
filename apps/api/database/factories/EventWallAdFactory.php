<?php

namespace Database\Factories;

use App\Modules\Wall\Models\EventWallAd;
use App\Modules\Wall\Models\EventWallSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventWallAd>
 */
class EventWallAdFactory extends Factory
{
    protected $model = EventWallAd::class;

    public function definition(): array
    {
        return [
            'event_wall_setting_id' => EventWallSetting::factory(),
            'file_path' => 'wall/ads/' . fake()->uuid() . '.jpg',
            'media_type' => fake()->randomElement(['image', 'video']),
            'duration_seconds' => fake()->randomElement([5, 10, 15, 30]),
            'position' => fake()->numberBetween(0, 10),
            'is_active' => true,
        ];
    }

    public function image(): static
    {
        return $this->state(fn () => ['media_type' => 'image']);
    }

    public function video(): static
    {
        return $this->state(fn () => ['media_type' => 'video']);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
