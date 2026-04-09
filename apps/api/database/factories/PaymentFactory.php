<?php

namespace Database\Factories;

use App\Modules\Billing\Enums\PaymentStatus;
use App\Modules\Billing\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'billing_order_id' => BillingOrderFactory::new(),
            'subscription_id' => null,
            'invoice_id' => null,
            'status' => PaymentStatus::Paid->value,
            'amount_cents' => fake()->numberBetween(9900, 39900),
            'currency' => 'BRL',
            'gateway_provider' => 'manual',
            'gateway_payment_id' => fake()->uuid(),
            'gateway_invoice_id' => null,
            'gateway_charge_status' => PaymentStatus::Paid->value,
            'card_brand' => null,
            'card_last_four' => null,
            'attempt_sequence' => 1,
            'paid_at' => now(),
            'raw_payload_json' => [],
        ];
    }
}
