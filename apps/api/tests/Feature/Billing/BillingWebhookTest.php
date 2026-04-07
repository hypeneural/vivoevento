<?php

use App\Modules\Billing\Actions\ProcessBillingWebhookAction;
use App\Modules\Billing\Actions\RegisterBillingGatewayPaymentAction;
use App\Modules\Billing\Enums\BillingOrderStatus;
use App\Modules\Billing\Enums\EventAccessGrantStatus;
use App\Modules\Billing\Enums\EventPackageAudience;
use App\Modules\Billing\Jobs\ProcessBillingWebhookJob;
use App\Modules\Billing\Models\BillingGatewayEvent;
use App\Modules\Billing\Models\BillingOrder;
use App\Modules\Billing\Models\BillingOrderNotification;
use App\Modules\Billing\Models\EventPackage;
use App\Modules\Events\Models\Event;
use App\Modules\Plans\Models\Plan;
use App\Modules\WhatsApp\Jobs\SendWhatsAppMessageJob;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use Illuminate\Support\Facades\Queue;

it('accepts a pagarme webhook, stores the raw event and queues async processing', function () {
    $this->seedPermissions();

    config()->set('services.pagarme.webhook_basic_auth_user', 'eventovivos');
    config()->set('services.pagarme.webhook_basic_auth_password', '!@#159!@#Mudar');

    Queue::fake();

    $order = BillingOrder::factory()->create([
        'mode' => 'event_package',
        'status' => 'pending_payment',
        'payment_method' => 'pix',
        'gateway_provider' => 'pagarme',
        'gateway_order_id' => 'or_test_123',
        'metadata_json' => [
            'journey' => 'public_event_checkout',
            'package_id' => 12,
            'package_code' => 'pkg-12',
        ],
    ]);

    $response = $this->apiPost('/webhooks/billing/pagarme', pagarmeOrderPaidWebhookPayload($order, [
        'gateway_order_id' => 'or_test_123',
        'gateway_charge_id' => 'ch_test_123',
        'gateway_transaction_id' => 'tx_test_123',
    ]), pagarmeWebhookHeaders());

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.accepted', true);
    $response->assertJsonPath('data.duplicate', false);
    $response->assertJsonPath('data.queued', true);
    $response->assertJsonPath('data.gateway_event.event_type', 'payment.paid');

    $gatewayEvent = BillingGatewayEvent::query()->firstOrFail();

    $this->assertDatabaseHas('billing_gateway_events', [
        'id' => $gatewayEvent->id,
        'provider_key' => 'pagarme',
        'event_key' => 'hook_order_paid_'.$order->id,
        'event_type' => 'payment.paid',
        'status' => 'pending',
        'billing_order_id' => $order->id,
        'gateway_order_id' => 'or_test_123',
        'gateway_charge_id' => 'ch_test_123',
        'gateway_transaction_id' => 'tx_test_123',
    ]);

    Queue::assertPushedOn('billing', ProcessBillingWebhookJob::class);
    Queue::assertPushed(ProcessBillingWebhookJob::class, function (ProcessBillingWebhookJob $job) use ($gatewayEvent) {
        return $job->gatewayEventId === $gatewayEvent->id;
    });
});

it('accepts a pagarme webhook when code is not a billing uuid and resolves by gateway_order_id', function () {
    config()->set('services.pagarme.webhook_basic_auth_user', 'eventovivos');
    config()->set('services.pagarme.webhook_basic_auth_password', '!@#159!@#Mudar');

    Queue::fake();

    $order = BillingOrder::factory()->create([
        'mode' => 'event_package',
        'status' => 'pending_payment',
        'payment_method' => 'credit_card',
        'gateway_provider' => 'pagarme',
        'gateway_order_id' => 'or_test_fallback_123',
    ]);

    $payload = pagarmeOrderPaymentFailedWebhookPayload($order, [
        'gateway_order_id' => 'or_test_fallback_123',
        'gateway_charge_id' => 'ch_test_fallback_123',
        'gateway_transaction_id' => 'tx_test_fallback_123',
    ]);

    unset($payload['data']['metadata']);
    $payload['data']['code'] = 'pix-hml-20260404';

    $response = $this->apiPost('/webhooks/billing/pagarme', $payload, pagarmeWebhookHeaders());

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.accepted', true);
    $response->assertJsonPath('data.duplicate', false);
    $response->assertJsonPath('data.gateway_event.gateway_order_id', 'or_test_fallback_123');

    $this->assertDatabaseHas('billing_gateway_events', [
        'provider_key' => 'pagarme',
        'event_key' => 'hook_order_failed_'.$order->id,
        'billing_order_id' => $order->id,
        'gateway_order_id' => 'or_test_fallback_123',
        'gateway_charge_id' => 'ch_test_fallback_123',
        'gateway_transaction_id' => 'tx_test_fallback_123',
    ]);

    Queue::assertPushedOn('billing', ProcessBillingWebhookJob::class);
});

it('treats a replayed pagarme webhook as duplicate and does not queue it twice', function () {
    config()->set('services.pagarme.webhook_basic_auth_user', 'eventovivos');
    config()->set('services.pagarme.webhook_basic_auth_password', '!@#159!@#Mudar');

    Queue::fake();

    $order = BillingOrder::factory()->create([
        'mode' => 'event_package',
        'status' => 'pending_payment',
        'payment_method' => 'pix',
        'gateway_provider' => 'pagarme',
        'gateway_order_id' => 'or_test_replay_123',
    ]);

    $payload = pagarmeOrderPaidWebhookPayload($order, [
        'gateway_order_id' => 'or_test_replay_123',
        'gateway_charge_id' => 'ch_test_replay_123',
        'gateway_transaction_id' => 'tx_test_replay_123',
    ]);

    $firstResponse = $this->apiPost('/webhooks/billing/pagarme', $payload, pagarmeWebhookHeaders());
    $secondResponse = $this->apiPost('/webhooks/billing/pagarme', $payload, pagarmeWebhookHeaders());

    $this->assertApiSuccess($firstResponse);
    $this->assertApiSuccess($secondResponse);

    $firstResponse->assertJsonPath('data.duplicate', false);
    $firstResponse->assertJsonPath('data.queued', true);
    $secondResponse->assertJsonPath('data.duplicate', true);
    $secondResponse->assertJsonPath('data.queued', false);

    expect(BillingGatewayEvent::query()->count())->toBe(1);

    Queue::assertPushedTimes(ProcessBillingWebhookJob::class, 1);
});

it('rejects a pagarme webhook when basic auth credentials are invalid', function () {
    config()->set('services.pagarme.webhook_basic_auth_user', 'eventovivos');
    config()->set('services.pagarme.webhook_basic_auth_password', '!@#159!@#Mudar');

    $order = BillingOrder::factory()->create([
        'mode' => 'event_package',
        'status' => 'pending_payment',
        'payment_method' => 'pix',
        'gateway_provider' => 'pagarme',
        'gateway_order_id' => 'or_test_123',
    ]);

    $response = $this->apiPost('/webhooks/billing/pagarme', pagarmeOrderPaidWebhookPayload($order), [
        'Authorization' => 'Basic '.base64_encode('invalid:credentials'),
    ]);

    $this->assertApiUnauthorized($response);
    $this->assertDatabaseCount('billing_gateway_events', 0);
});

it('processes a manual subscription payment webhook idempotently', function () {
    [$user, $organization] = $this->actingAsOwner();

    $plan = Plan::create([
        'code' => 'webhook-pro',
        'name' => 'Webhook Pro',
        'audience' => 'b2b',
        'status' => 'active',
    ]);

    $plan->prices()->create([
        'billing_cycle' => 'monthly',
        'currency' => 'BRL',
        'amount_cents' => 12900,
        'is_default' => true,
    ]);

    $order = BillingOrder::create([
        'organization_id' => $organization->id,
        'buyer_user_id' => $user->id,
        'mode' => 'subscription',
        'status' => 'pending_payment',
        'currency' => 'BRL',
        'total_cents' => 12900,
        'gateway_provider' => 'manual',
        'gateway_order_id' => 'manual-subscription-webhook-test',
        'metadata_json' => [
            'journey' => 'subscription_checkout',
            'plan_id' => $plan->id,
            'plan_code' => $plan->code,
            'billing_cycle' => 'monthly',
        ],
    ]);

    $order->items()->create([
        'item_type' => 'subscription_plan',
        'reference_id' => $plan->id,
        'description' => 'Plano Webhook Pro',
        'quantity' => 1,
        'unit_amount_cents' => 12900,
        'total_amount_cents' => 12900,
        'snapshot_json' => [
            'plan' => [
                'id' => $plan->id,
                'code' => $plan->code,
                'name' => $plan->name,
            ],
        ],
    ]);

    $result = app(ProcessBillingWebhookAction::class)->execute('manual', [
        'event_key' => 'evt_subscription_paid_001',
        'type' => 'payment.paid',
        'billing_order_uuid' => $order->uuid,
        'gateway_order_id' => $order->gateway_order_id,
        'gateway_payment_id' => 'pay_subscription_001',
        'occurred_at' => now()->toISOString(),
        'data' => [
            'source' => 'manual_test',
        ],
    ]);

    expect($result['duplicate'])->toBeFalse();
    expect(data_get($result, 'result.action'))->toBe('payment_registered');

    $this->assertDatabaseHas('subscriptions', [
        'organization_id' => $organization->id,
        'plan_id' => $plan->id,
        'status' => 'active',
    ]);

    $this->assertDatabaseHas('billing_orders', [
        'id' => $order->id,
        'status' => 'paid',
    ]);

    $this->assertDatabaseCount('payments', 1);
    $this->assertDatabaseCount('invoices', 1);

    $duplicate = app(ProcessBillingWebhookAction::class)->execute('manual', [
        'event_key' => 'evt_subscription_paid_001',
        'type' => 'payment.paid',
        'billing_order_uuid' => $order->uuid,
        'gateway_order_id' => $order->gateway_order_id,
        'gateway_payment_id' => 'pay_subscription_001',
        'occurred_at' => now()->toISOString(),
        'data' => [
            'source' => 'manual_test',
        ],
    ]);

    expect($duplicate['duplicate'])->toBeTrue();
    $this->assertDatabaseCount('payments', 1);
    $this->assertDatabaseCount('invoices', 1);
});

it('processes a queued pagarme paid event idempotently and activates the event', function () {
    Queue::fake();

    [$user, $organization] = $this->actingAsOwner();

    $instance = WhatsAppInstance::factory()->connected()->create([
        'is_default' => true,
    ]);

    config()->set('billing.payment_notifications.enabled', true);
    config()->set('billing.payment_notifications.whatsapp_instance_id', $instance->id);

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'commercial_mode' => 'none',
    ]);

    $package = EventPackage::factory()->create([
        'code' => 'premium-public-event',
        'name' => 'Premium Public Event',
        'target_audience' => EventPackageAudience::Both->value,
        'is_active' => true,
    ]);

    $package->prices()->create([
        'billing_mode' => 'one_time',
        'currency' => 'BRL',
        'amount_cents' => 19900,
        'is_active' => true,
        'is_default' => true,
    ]);

    $package->features()->createMany([
        ['feature_key' => 'wall.enabled', 'feature_value' => 'true'],
        ['feature_key' => 'play.enabled', 'feature_value' => 'true'],
        ['feature_key' => 'media.retention_days', 'feature_value' => '90'],
    ]);

    $order = BillingOrder::create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'buyer_user_id' => $user->id,
        'mode' => 'event_package',
        'status' => 'pending_payment',
        'currency' => 'BRL',
        'total_cents' => 19900,
        'payment_method' => 'pix',
        'gateway_provider' => 'pagarme',
        'gateway_order_id' => 'or_test_123',
        'customer_snapshot_json' => [
            'name' => 'Mariana Alves',
            'phone' => '5548999881111',
            'email' => 'mariana@example.com',
        ],
        'metadata_json' => [
            'journey' => 'public_event_checkout',
            'package_id' => $package->id,
            'package_code' => $package->code,
        ],
    ]);

    $order->items()->create([
        'item_type' => 'event_package',
        'reference_id' => $package->id,
        'description' => 'Pacote Premium Public Event',
        'quantity' => 1,
        'unit_amount_cents' => 19900,
        'total_amount_cents' => 19900,
        'snapshot_json' => [
            'package' => [
                'id' => $package->id,
                'code' => $package->code,
                'name' => $package->name,
            ],
        ],
    ]);

    $gatewayEvent = BillingGatewayEvent::create([
        'provider_key' => 'pagarme',
        'event_key' => 'hook_order_paid_'.$order->id,
        'event_type' => 'payment.paid',
        'status' => 'pending',
        'billing_order_id' => $order->id,
        'gateway_order_id' => 'or_test_123',
        'payload_json' => pagarmeOrderPaidWebhookPayload($order, [
            'gateway_order_id' => 'or_test_123',
            'gateway_charge_id' => 'ch_test_123',
            'gateway_transaction_id' => 'tx_test_123',
        ]),
        'headers_json' => [],
        'occurred_at' => now(),
    ]);

    $result = app(ProcessBillingWebhookAction::class)->executeRecorded($gatewayEvent->fresh());

    expect($result['duplicate'])->toBeFalse();
    expect(data_get($result, 'result.action'))->toBe('payment_registered');

    $this->assertDatabaseHas('billing_orders', [
        'id' => $order->id,
        'status' => 'paid',
        'gateway_provider' => 'pagarme',
        'gateway_order_id' => 'or_test_123',
        'gateway_charge_id' => 'ch_test_123',
        'gateway_transaction_id' => 'tx_test_123',
        'gateway_status' => 'paid',
    ]);

    $this->assertDatabaseHas('payments', [
        'billing_order_id' => $order->id,
        'status' => 'paid',
        'payment_method' => 'pix',
        'gateway_provider' => 'pagarme',
        'gateway_order_id' => 'or_test_123',
        'gateway_charge_id' => 'ch_test_123',
        'gateway_transaction_id' => 'tx_test_123',
        'gateway_status' => 'paid',
        'qr_code_url' => 'https://pagar.me/qr/ch_test_123.png',
    ]);

    $this->assertDatabaseHas('event_purchases', [
        'billing_order_id' => $order->id,
        'package_id' => $package->id,
        'status' => 'paid',
    ]);

    $this->assertDatabaseHas('event_access_grants', [
        'event_id' => $event->id,
        'package_id' => $package->id,
        'source_type' => 'event_purchase',
        'status' => 'active',
    ]);

    $this->assertDatabaseHas('billing_gateway_events', [
        'id' => $gatewayEvent->id,
        'status' => 'processed',
    ]);

    $this->assertDatabaseHas('billing_order_notifications', [
        'billing_order_id' => $order->id,
        'notification_type' => 'payment_paid',
        'channel' => 'whatsapp',
        'status' => 'queued',
        'recipient_phone' => '5548999881111',
        'whatsapp_instance_id' => $instance->id,
    ]);

    $paidNotification = BillingOrderNotification::query()
        ->where('billing_order_id', $order->id)
        ->where('notification_type', 'payment_paid')
        ->firstOrFail();

    expect($paidNotification->whatsapp_message_id)->not()->toBeNull();

    $this->assertDatabaseHas('whatsapp_messages', [
        'id' => $paidNotification->whatsapp_message_id,
        'instance_id' => $instance->id,
        'recipient_phone' => '5548999881111',
    ]);

    $event->refresh();

    expect($event->commercial_mode?->value)->toBe('single_purchase');
    expect($event->current_entitlements_json['modules']['wall'] ?? null)->toBeTrue();
    expect($event->current_entitlements_json['modules']['play'] ?? null)->toBeTrue();

    $duplicate = app(ProcessBillingWebhookAction::class)->executeRecorded($gatewayEvent->fresh());

    expect($duplicate['duplicate'])->toBeTrue();
    $this->assertDatabaseCount('payments', 1);
    $this->assertDatabaseCount('event_purchases', 1);
    expect(BillingOrderNotification::query()
        ->where('billing_order_id', $order->id)
        ->where('notification_type', 'payment_paid')
        ->count())->toBe(1);

    Queue::assertPushedTimes(SendWhatsAppMessageJob::class, 1);
});

it('processes a queued pagarme failed event and marks the order as failed locally', function () {
    Queue::fake();

    $instance = WhatsAppInstance::factory()->connected()->create([
        'is_default' => true,
    ]);

    config()->set('billing.payment_notifications.enabled', true);
    config()->set('billing.payment_notifications.whatsapp_instance_id', $instance->id);

    $order = BillingOrder::factory()->create([
        'mode' => 'event_package',
        'status' => 'pending_payment',
        'payment_method' => 'credit_card',
        'gateway_provider' => 'pagarme',
        'gateway_order_id' => 'or_test_failed_123',
        'customer_snapshot_json' => [
            'name' => 'Camila Rocha',
            'phone' => '5548999771111',
            'email' => 'camila@example.com',
        ],
    ]);

    $gatewayEvent = BillingGatewayEvent::create([
        'provider_key' => 'pagarme',
        'event_key' => 'hook_order_failed_'.$order->id,
        'event_type' => 'payment.failed',
        'status' => 'pending',
        'billing_order_id' => $order->id,
        'gateway_order_id' => 'or_test_failed_123',
        'payload_json' => pagarmeOrderPaymentFailedWebhookPayload($order, [
            'gateway_order_id' => 'or_test_failed_123',
            'gateway_charge_id' => 'ch_test_failed_123',
            'gateway_transaction_id' => 'tx_test_failed_123',
            'acquirer_message' => 'Nao autorizado',
            'acquirer_return_code' => '51',
        ]),
        'headers_json' => [],
        'occurred_at' => now(),
    ]);

    $result = app(ProcessBillingWebhookAction::class)->executeRecorded($gatewayEvent->fresh());

    expect($result['duplicate'])->toBeFalse();
    expect(data_get($result, 'result.action'))->toBe('payment_failed');

    $order->refresh();

    expect($order->status)->toBe(BillingOrderStatus::Failed);
    expect($order->failed_at)->not()->toBeNull();
    expect($order->gateway_charge_id)->toBe('ch_test_failed_123');
    expect($order->gateway_transaction_id)->toBe('tx_test_failed_123');
    expect($order->gateway_status)->toBe('failed');

    $this->assertDatabaseHas('payments', [
        'billing_order_id' => $order->id,
        'status' => 'failed',
        'gateway_provider' => 'pagarme',
        'gateway_order_id' => 'or_test_failed_123',
        'gateway_charge_id' => 'ch_test_failed_123',
        'gateway_transaction_id' => 'tx_test_failed_123',
        'gateway_status' => 'failed',
        'acquirer_message' => 'Nao autorizado',
        'acquirer_return_code' => '51',
    ]);

    $this->assertDatabaseHas('billing_gateway_events', [
        'id' => $gatewayEvent->id,
        'status' => 'processed',
    ]);

    $this->assertDatabaseHas('billing_order_notifications', [
        'billing_order_id' => $order->id,
        'notification_type' => 'payment_failed',
        'channel' => 'whatsapp',
        'status' => 'queued',
        'recipient_phone' => '5548999771111',
        'whatsapp_instance_id' => $instance->id,
    ]);

    Queue::assertPushed(SendWhatsAppMessageJob::class);
});

it('processes a queued pagarme canceled event and marks the one-time order as canceled locally', function () {
    $order = BillingOrder::factory()->create([
        'mode' => 'event_package',
        'status' => 'pending_payment',
        'payment_method' => 'pix',
        'gateway_provider' => 'pagarme',
        'gateway_order_id' => 'or_test_canceled_123',
        'customer_snapshot_json' => [
            'name' => 'Camila Rocha',
            'phone' => '5548999771111',
            'email' => 'camila@example.com',
        ],
    ]);

    $gatewayEvent = BillingGatewayEvent::create([
        'provider_key' => 'pagarme',
        'event_key' => 'hook_order_canceled_'.$order->id,
        'event_type' => 'checkout.canceled',
        'status' => 'pending',
        'billing_order_id' => $order->id,
        'gateway_order_id' => 'or_test_canceled_123',
        'payload_json' => pagarmeOrderCanceledWebhookPayload($order, [
            'gateway_order_id' => 'or_test_canceled_123',
            'gateway_charge_id' => 'ch_test_canceled_123',
            'gateway_transaction_id' => 'tx_test_canceled_123',
        ]),
        'headers_json' => [],
        'occurred_at' => now(),
    ]);

    $result = app(ProcessBillingWebhookAction::class)->executeRecorded($gatewayEvent->fresh());

    expect($result['duplicate'])->toBeFalse();
    expect(data_get($result, 'result.action'))->toBe('order_canceled');

    $order->refresh();

    expect($order->status)->toBe(BillingOrderStatus::Canceled);
    expect($order->canceled_at)->not()->toBeNull();
    expect($order->gateway_order_id)->toBe('or_test_canceled_123');
    expect($order->gateway_charge_id)->toBe('ch_test_canceled_123');
    expect($order->gateway_transaction_id)->toBe('tx_test_canceled_123');
    expect($order->gateway_status)->toBe('canceled');

    $this->assertDatabaseHas('billing_gateway_events', [
        'id' => $gatewayEvent->id,
        'status' => 'processed',
        'gateway_order_id' => 'or_test_canceled_123',
        'gateway_charge_id' => 'ch_test_canceled_123',
        'gateway_transaction_id' => 'tx_test_canceled_123',
    ]);

    $this->assertDatabaseCount('payments', 0);
    $this->assertDatabaseCount('event_purchases', 0);
});

it('processes a queued pagarme refunded event and revokes the event access locally', function () {
    Queue::fake();

    [$user, $organization] = $this->actingAsOwner();

    $instance = WhatsAppInstance::factory()->connected()->create([
        'is_default' => true,
    ]);

    config()->set('billing.payment_notifications.enabled', true);
    config()->set('billing.payment_notifications.whatsapp_instance_id', $instance->id);

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'commercial_mode' => 'none',
    ]);

    $package = EventPackage::factory()->create([
        'code' => 'premium-public-event',
        'name' => 'Premium Public Event',
        'target_audience' => EventPackageAudience::Both->value,
        'is_active' => true,
    ]);

    $package->prices()->create([
        'billing_mode' => 'one_time',
        'currency' => 'BRL',
        'amount_cents' => 19900,
        'is_active' => true,
        'is_default' => true,
    ]);

    $package->features()->createMany([
        ['feature_key' => 'wall.enabled', 'feature_value' => 'true'],
        ['feature_key' => 'play.enabled', 'feature_value' => 'true'],
    ]);

    $order = BillingOrder::create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'buyer_user_id' => $user->id,
        'mode' => 'event_package',
        'status' => 'pending_payment',
        'currency' => 'BRL',
        'total_cents' => 19900,
        'payment_method' => 'credit_card',
        'gateway_provider' => 'pagarme',
        'gateway_order_id' => 'or_test_refund_123',
        'customer_snapshot_json' => [
            'name' => 'Camila Rocha',
            'phone' => '5548999771111',
            'email' => 'camila@example.com',
        ],
        'metadata_json' => [
            'journey' => 'public_event_checkout',
            'package_id' => $package->id,
            'package_code' => $package->code,
        ],
    ]);

    $order->items()->create([
        'item_type' => 'event_package',
        'reference_id' => $package->id,
        'description' => 'Pacote Premium Public Event',
        'quantity' => 1,
        'unit_amount_cents' => 19900,
        'total_amount_cents' => 19900,
        'snapshot_json' => [
            'package' => [
                'id' => $package->id,
                'code' => $package->code,
                'name' => $package->name,
            ],
        ],
    ]);

    app(RegisterBillingGatewayPaymentAction::class)->execute($order, [
        'gateway_provider' => 'pagarme',
        'gateway_order_id' => 'or_test_refund_123',
        'gateway_payment_id' => 'ch_test_refund_123',
        'gateway_charge_id' => 'ch_test_refund_123',
        'gateway_transaction_id' => 'tx_test_paid_123',
        'paid_at' => now()->subMinute(),
        'payment_payload' => ['source' => 'seed_paid_payment'],
        'gateway_status' => 'paid',
    ]);

    $order->refresh();

    $gatewayEvent = BillingGatewayEvent::create([
        'provider_key' => 'pagarme',
        'event_key' => 'hook_charge_refunded_'.$order->id,
        'event_type' => 'payment.refunded',
        'status' => 'pending',
        'billing_order_id' => $order->id,
        'gateway_order_id' => 'or_test_refund_123',
        'gateway_charge_id' => 'ch_test_refund_123',
        'payload_json' => pagarmeChargeRefundedWebhookPayload($order, [
            'gateway_order_id' => 'or_test_refund_123',
            'gateway_charge_id' => 'ch_test_refund_123',
            'gateway_transaction_id' => 'tx_test_refund_123',
        ]),
        'headers_json' => [],
        'occurred_at' => now(),
    ]);

    $result = app(ProcessBillingWebhookAction::class)->executeRecorded($gatewayEvent->fresh());

    expect($result['duplicate'])->toBeFalse();
    expect(data_get($result, 'result.action'))->toBe('payment_refunded');

    $order->refresh();
    $event->refresh();

    expect($order->status)->toBe(BillingOrderStatus::Refunded);
    expect($order->refunded_at)->not()->toBeNull();
    expect($order->gateway_status)->toBe('refunded');

    $this->assertDatabaseHas('payments', [
        'billing_order_id' => $order->id,
        'status' => 'refunded',
        'gateway_provider' => 'pagarme',
        'gateway_charge_id' => 'ch_test_refund_123',
        'gateway_transaction_id' => 'tx_test_refund_123',
        'gateway_status' => 'refunded',
    ]);

    $this->assertDatabaseHas('invoices', [
        'billing_order_id' => $order->id,
        'status' => 'refunded',
    ]);

    $this->assertDatabaseHas('event_purchases', [
        'billing_order_id' => $order->id,
        'package_id' => $package->id,
        'status' => 'refunded',
    ]);

    $this->assertDatabaseHas('event_access_grants', [
        'event_id' => $event->id,
        'package_id' => $package->id,
        'source_type' => 'event_purchase',
        'status' => EventAccessGrantStatus::Revoked->value,
    ]);

    expect($event->commercial_mode?->value)->toBe('none');

    $this->assertDatabaseHas('billing_order_notifications', [
        'billing_order_id' => $order->id,
        'notification_type' => 'payment_refunded',
        'channel' => 'whatsapp',
        'status' => 'queued',
        'recipient_phone' => '5548999771111',
        'whatsapp_instance_id' => $instance->id,
    ]);

    Queue::assertPushed(SendWhatsAppMessageJob::class);
});

function pagarmeWebhookHeaders(?string $username = 'eventovivos', ?string $password = '!@#159!@#Mudar'): array
{
    return [
        'Authorization' => 'Basic '.base64_encode(sprintf('%s:%s', $username, $password)),
    ];
}

function pagarmeOrderPaidWebhookPayload(BillingOrder $order, array $overrides = []): array
{
    $gatewayOrderId = $overrides['gateway_order_id'] ?? 'or_test_123';
    $gatewayChargeId = $overrides['gateway_charge_id'] ?? 'ch_test_123';
    $gatewayTransactionId = $overrides['gateway_transaction_id'] ?? 'tx_test_123';

    return [
        'id' => 'hook_order_paid_'.$order->id,
        'type' => 'order.paid',
        'created_at' => now()->toISOString(),
        'data' => [
            'id' => $gatewayOrderId,
            'code' => $order->uuid,
            'status' => 'paid',
            'metadata' => [
                'billing_order_uuid' => $order->uuid,
            ],
            'charges' => [
                [
                    'id' => $gatewayChargeId,
                    'status' => 'paid',
                    'payment_method' => $order->payment_method ?? 'pix',
                    'last_transaction' => [
                        'id' => $gatewayTransactionId,
                        'status' => 'paid',
                        'qr_code' => '00020101021226890014br.gov.bcb.pix2567pix.example/qr/123',
                        'qr_code_url' => 'https://pagar.me/qr/ch_test_123.png',
                        'expires_at' => now()->addMinutes(30)->toISOString(),
                    ],
                ],
            ],
        ],
    ];
}

function pagarmeOrderPaymentFailedWebhookPayload(BillingOrder $order, array $overrides = []): array
{
    $gatewayOrderId = $overrides['gateway_order_id'] ?? 'or_test_failed_123';
    $gatewayChargeId = $overrides['gateway_charge_id'] ?? 'ch_test_failed_123';
    $gatewayTransactionId = $overrides['gateway_transaction_id'] ?? 'tx_test_failed_123';

    return [
        'id' => 'hook_order_failed_'.$order->id,
        'type' => 'order.payment_failed',
        'created_at' => now()->toISOString(),
        'data' => [
            'id' => $gatewayOrderId,
            'code' => $order->uuid,
            'status' => 'failed',
            'metadata' => [
                'billing_order_uuid' => $order->uuid,
            ],
            'charges' => [
                [
                    'id' => $gatewayChargeId,
                    'status' => 'failed',
                    'payment_method' => $order->payment_method ?? 'credit_card',
                    'last_transaction' => [
                        'id' => $gatewayTransactionId,
                        'status' => 'failed',
                        'acquirer_message' => $overrides['acquirer_message'] ?? 'Nao autorizado',
                        'acquirer_return_code' => $overrides['acquirer_return_code'] ?? '51',
                    ],
                ],
            ],
        ],
    ];
}

function pagarmeOrderCanceledWebhookPayload(BillingOrder $order, array $overrides = []): array
{
    $gatewayOrderId = $overrides['gateway_order_id'] ?? 'or_test_canceled_123';
    $gatewayChargeId = $overrides['gateway_charge_id'] ?? 'ch_test_canceled_123';
    $gatewayTransactionId = $overrides['gateway_transaction_id'] ?? 'tx_test_canceled_123';

    return [
        'id' => 'hook_order_canceled_'.$order->id,
        'type' => 'order.canceled',
        'created_at' => now()->toISOString(),
        'data' => [
            'id' => $gatewayOrderId,
            'code' => $order->uuid,
            'status' => 'canceled',
            'metadata' => [
                'billing_order_uuid' => $order->uuid,
            ],
            'charges' => [
                [
                    'id' => $gatewayChargeId,
                    'status' => 'canceled',
                    'payment_method' => $order->payment_method ?? 'pix',
                    'last_transaction' => [
                        'id' => $gatewayTransactionId,
                        'status' => 'refunded',
                    ],
                ],
            ],
        ],
    ];
}

function pagarmeChargeRefundedWebhookPayload(BillingOrder $order, array $overrides = []): array
{
    $gatewayOrderId = $overrides['gateway_order_id'] ?? 'or_test_refund_123';
    $gatewayChargeId = $overrides['gateway_charge_id'] ?? 'ch_test_refund_123';
    $gatewayTransactionId = $overrides['gateway_transaction_id'] ?? 'tx_test_refund_123';

    return [
        'id' => 'hook_charge_refunded_'.$order->id,
        'type' => 'charge.refunded',
        'created_at' => now()->toISOString(),
        'data' => [
            'id' => $gatewayChargeId,
            'status' => 'refunded',
            'payment_method' => $order->payment_method ?? 'credit_card',
            'last_transaction' => [
                'id' => $gatewayTransactionId,
                'status' => 'refunded',
            ],
            'order' => [
                'id' => $gatewayOrderId,
                'metadata' => [
                    'billing_order_uuid' => $order->uuid,
                ],
            ],
            'metadata' => [
                'billing_order_uuid' => $order->uuid,
            ],
        ],
    ];
}
