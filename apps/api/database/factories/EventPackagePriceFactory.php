<?php

namespace Database\Factories;

use App\Modules\Billing\Enums\EventPackageBillingMode;
use App\Modules\Billing\Models\EventPackagePrice;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventPackagePriceFactory extends Factory
{
    protected $model = EventPackagePrice::class;

    public function definition(): array
    {
        return [
            'event_package_id' => EventPackageFactory::new(),
            'billing_mode' => EventPackageBillingMode::OneTime->value,
            'currency' => 'BRL',
            'amount_cents' => fake()->numberBetween(4900, 29900),
            'is_active' => true,
            'is_default' => true,
        ];
    }
}
