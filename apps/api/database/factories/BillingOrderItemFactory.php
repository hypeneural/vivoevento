<?php

namespace Database\Factories;

use App\Modules\Billing\Models\BillingOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class BillingOrderItemFactory extends Factory
{
    protected $model = BillingOrderItem::class;

    public function definition(): array
    {
        return [
            'billing_order_id' => BillingOrderFactory::new(),
            'item_type' => 'event_package',
            'reference_id' => EventPackageFactory::new(),
            'description' => fake()->sentence(3),
            'quantity' => 1,
            'unit_amount_cents' => fake()->numberBetween(9900, 39900),
            'total_amount_cents' => fake()->numberBetween(9900, 39900),
            'snapshot_json' => [],
        ];
    }
}
