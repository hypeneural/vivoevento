<?php

namespace Database\Factories;

use App\Modules\Partners\Models\PartnerProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class PartnerProfileFactory extends Factory
{
    protected $model = PartnerProfile::class;

    public function definition(): array
    {
        return [
            'organization_id' => OrganizationFactory::new(),
            'segment' => fake()->randomElement(['cerimonialista', 'fotografo', 'agencia']),
            'business_stage' => fake()->randomElement(['new', 'growing', 'mature']),
            'account_owner_user_id' => UserFactory::new(),
            'notes' => fake()->sentence(),
            'tags_json' => ['vip', 'b2b'],
            'onboarded_at' => now()->subDays(fake()->numberBetween(1, 90)),
        ];
    }
}
