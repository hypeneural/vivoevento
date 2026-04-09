<?php

namespace Database\Factories;

use App\Modules\Billing\Models\BillingGatewayEvent;
use App\Modules\Billing\Models\BillingOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BillingGatewayEvent>
 */
class BillingGatewayEventFactory extends Factory
{
    protected $model = BillingGatewayEvent::class;

    public function definition(): array
    {
        return [
            'provider_key' => 'manual',
            'event_key' => 'evt_' . Str::lower(Str::random(16)),
            'hook_id' => null,
            'event_type' => 'payment.paid',
            'status' => 'pending',
            'billing_order_id' => BillingOrder::factory(),
            'gateway_order_id' => 'gw_' . Str::lower(Str::random(12)),
            'gateway_subscription_id' => null,
            'gateway_invoice_id' => null,
            'gateway_cycle_id' => null,
            'gateway_customer_id' => null,
            'processed_at' => null,
            'headers_json' => [],
            'payload_hash' => null,
            'payload_json' => [
                'event_key' => 'evt_' . Str::lower(Str::random(16)),
                'type' => 'payment.paid',
            ],
            'result_json' => null,
        ];
    }
}
