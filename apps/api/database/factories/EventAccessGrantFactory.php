<?php

namespace Database\Factories;

use App\Modules\Billing\Enums\EntitlementMergeStrategy;
use App\Modules\Billing\Enums\EventAccessGrantSourceType;
use App\Modules\Billing\Enums\EventAccessGrantStatus;
use App\Modules\Billing\Models\EventAccessGrant;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventAccessGrantFactory extends Factory
{
    protected $model = EventAccessGrant::class;

    public function definition(): array
    {
        $sourceType = fake()->randomElement([
            EventAccessGrantSourceType::Trial,
            EventAccessGrantSourceType::Bonus,
            EventAccessGrantSourceType::ManualOverride,
        ]);

        return [
            'organization_id' => OrganizationFactory::new(),
            'event_id' => EventFactory::new(),
            'source_type' => $sourceType->value,
            'source_id' => null,
            'package_id' => null,
            'status' => EventAccessGrantStatus::Active->value,
            'priority' => $sourceType->defaultPriority(),
            'merge_strategy' => EntitlementMergeStrategy::Expand->value,
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addWeek(),
            'features_snapshot_json' => [],
            'limits_snapshot_json' => [],
            'granted_by_user_id' => UserFactory::new(),
            'notes' => fake()->sentence(),
            'metadata_json' => [],
        ];
    }

    public function forEvent(\App\Modules\Events\Models\Event $event): static
    {
        return $this->state(fn () => [
            'event_id' => $event->id,
            'organization_id' => $event->organization_id,
        ]);
    }
}
