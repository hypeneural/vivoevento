<?php

namespace Database\Factories;

use App\Modules\Billing\Models\SubscriptionCycle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SubscriptionCycleFactory extends Factory
{
    protected $model = SubscriptionCycle::class;

    public function definition(): array
    {
        $billingAt = now();
        $periodEndAt = $billingAt->copy()->addMonth();

        return [
            'subscription_id' => SubscriptionFactory::new(),
            'gateway_cycle_id' => 'cy_' . Str::lower(Str::random(18)),
            'status' => 'billed',
            'billing_at' => $billingAt,
            'period_start_at' => $billingAt,
            'period_end_at' => $periodEndAt,
            'closed_at' => $periodEndAt,
            'raw_gateway_json' => [],
        ];
    }
}
