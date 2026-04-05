<?php

use App\Modules\Billing\Actions\RecordBillingGatewayWebhookAction;
use App\Modules\Billing\Models\BillingOrder;

it('falls back to gateway_order_id when billing_order_uuid is not a valid uuid', function () {
    $order = BillingOrder::factory()->create([
        'gateway_provider' => 'pagarme',
        'gateway_order_id' => 'or_test_non_uuid_123',
    ]);

    $event = app(RecordBillingGatewayWebhookAction::class)->execute([
        'provider_key' => 'pagarme',
        'event_key' => 'hook_non_uuid_'.$order->id,
        'event_type' => 'payment.failed',
        'billing_order_uuid' => 'pix-hml-20260404',
        'gateway_order_id' => 'or_test_non_uuid_123',
        'gateway_charge_id' => 'ch_test_non_uuid_123',
        'gateway_transaction_id' => 'tx_test_non_uuid_123',
        'occurred_at' => now(),
        'headers' => [],
        'payload' => [
            'id' => 'hook_non_uuid_'.$order->id,
        ],
    ]);

    expect($event->billing_order_id)->toBe($order->id);

    $this->assertDatabaseHas('billing_gateway_events', [
        'id' => $event->id,
        'billing_order_id' => $order->id,
        'gateway_order_id' => 'or_test_non_uuid_123',
    ]);
});
