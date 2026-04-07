<?php

namespace Database\Factories;

use App\Modules\Partners\Models\PartnerStat;
use Illuminate\Database\Eloquent\Factories\Factory;

class PartnerStatFactory extends Factory
{
    protected $model = PartnerStat::class;

    public function definition(): array
    {
        $subscriptionRevenue = fake()->numberBetween(0, 50000);
        $eventPackageRevenue = fake()->numberBetween(0, 50000);

        return [
            'organization_id' => OrganizationFactory::new(),
            'clients_count' => fake()->numberBetween(0, 20),
            'events_count' => fake()->numberBetween(0, 30),
            'active_events_count' => fake()->numberBetween(0, 15),
            'team_size' => fake()->numberBetween(1, 8),
            'active_bonus_grants_count' => fake()->numberBetween(0, 5),
            'subscription_plan_code' => fake()->randomElement(['starter', 'pro-parceiro', 'enterprise']),
            'subscription_plan_name' => fake()->randomElement(['Starter', 'Pro Parceiro', 'Enterprise']),
            'subscription_status' => fake()->randomElement(['trialing', 'active', 'canceled']),
            'subscription_billing_cycle' => fake()->randomElement(['monthly', 'yearly']),
            'subscription_revenue_cents' => $subscriptionRevenue,
            'event_package_revenue_cents' => $eventPackageRevenue,
            'total_revenue_cents' => $subscriptionRevenue + $eventPackageRevenue,
            'last_paid_invoice_at' => now()->subDays(fake()->numberBetween(0, 30)),
            'refreshed_at' => now()->subMinutes(fake()->numberBetween(0, 30)),
        ];
    }
}
