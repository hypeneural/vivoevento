<?php

use App\Modules\Billing\Models\BillingOrder;
use App\Modules\Billing\Services\Pagarme\PagarmeSubscriptionPayloadFactory;
use App\Modules\Plans\Models\Plan;
use App\Modules\Plans\Models\PlanPrice;

it('builds a recurring pagarme subscription payload using resolved plan customer and card ids', function () {
    $plan = Plan::create([
        'code' => 'partner-pro',
        'name' => 'Partner Pro',
        'audience' => 'b2b',
        'status' => 'active',
    ]);

    $planPrice = PlanPrice::create([
        'plan_id' => $plan->id,
        'billing_cycle' => 'monthly',
        'currency' => 'BRL',
        'amount_cents' => 19900,
        'gateway_plan_id' => 'plan_recurring_123',
        'is_default' => true,
    ]);

    $order = BillingOrder::factory()->create([
        'mode' => 'subscription',
        'payment_method' => 'credit_card',
        'customer_snapshot_json' => [
            'name' => 'Evento Vivo LTDA',
            'email' => 'billing@eventovivo.test',
            'document' => '12345678000199',
            'phone' => '5511999999999',
            'address' => [
                'street' => 'Rua A',
                'number' => '100',
                'district' => 'Centro',
                'zip_code' => '01001000',
                'city' => 'Sao Paulo',
                'state' => 'SP',
                'country' => 'BR',
            ],
        ],
        'metadata_json' => [
            'journey' => 'subscription_checkout',
        ],
    ]);

    $payload = app(PagarmeSubscriptionPayloadFactory::class)->build($order, $plan, $planPrice, [
        'payment_method' => 'credit_card',
        'gateway_plan_id' => 'plan_recurring_123',
        'gateway_customer_id' => 'cus_recurring_123',
        'gateway_card_id' => 'card_recurring_123',
    ]);

    expect($payload['code'])->toBe($order->uuid)
        ->and($payload['plan_id'])->toBe('plan_recurring_123')
        ->and($payload['payment_method'])->toBe('credit_card')
        ->and($payload['customer_id'])->toBe('cus_recurring_123')
        ->and($payload)->not->toHaveKey('customer')
        ->and($payload['card'])->toBe([
            'card_id' => 'card_recurring_123',
        ])
        ->and($payload['installments'])->toBe(1)
        ->and(data_get($payload, 'metadata.billing_order_id'))->toBe((string) $order->id)
        ->and(data_get($payload, 'metadata.plan_price_id'))->toBe((string) $planPrice->id);
});
