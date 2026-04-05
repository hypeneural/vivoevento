<?php

namespace Database\Factories;

use App\Modules\Events\Enums\EventCommercialMode;
use App\Modules\Events\Enums\EventStatus;
use App\Modules\Events\Enums\EventType;
use App\Modules\Events\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'organization_id' => OrganizationFactory::new(),
            'title' => $title,
            'slug' => Str::slug($title) . '-' . Str::random(4),
            'event_type' => fake()->randomElement(EventType::cases())->value,
            'status' => EventStatus::Draft->value,
            'visibility' => 'public',
            'moderation_mode' => 'manual',
            'starts_at' => now()->addDays(rand(1, 30)),
            'ends_at' => now()->addDays(rand(31, 60)),
            'location_name' => fake()->city(),
            'description' => fake()->paragraph(),
            'retention_days' => 30,
            'commercial_mode' => EventCommercialMode::None->value,
            'current_entitlements_json' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => EventStatus::Active->value]);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => EventStatus::Draft->value]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => EventStatus::Archived->value]);
    }

    public function noModeration(): static
    {
        return $this->state(fn () => ['moderation_mode' => 'none']);
    }

    public function manualModeration(): static
    {
        return $this->state(fn () => ['moderation_mode' => 'manual']);
    }

    public function aiModeration(): static
    {
        return $this->state(fn () => ['moderation_mode' => 'ai']);
    }
}
