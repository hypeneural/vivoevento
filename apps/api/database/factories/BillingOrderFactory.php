<?php

namespace Database\Factories;

use App\Modules\Billing\Enums\BillingOrderMode;
use App\Modules\Billing\Enums\BillingOrderStatus;
use App\Modules\Billing\Models\BillingOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BillingOrderFactory extends Factory
{
    protected $model = BillingOrder::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'organization_id' => OrganizationFactory::new(),
            'event_id' => EventFactory::new(),
            'buyer_user_id' => UserFactory::new(),
            'mode' => BillingOrderMode::EventPackage->value,
            'status' => BillingOrderStatus::PendingPayment->value,
            'currency' => 'BRL',
            'total_cents' => fake()->numberBetween(9900, 39900),
            'gateway_provider' => 'manual',
            'gateway_order_id' => null,
            'confirmed_at' => null,
            'metadata_json' => [],
        ];
    }
}
