<?php

use App\Modules\Billing\Enums\EventAccessGrantSourceType;
use App\Modules\Billing\Enums\EventAccessGrantStatus;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\PaymentStatus;
use App\Modules\Billing\Models\BillingOrder;
use App\Modules\Billing\Models\EventAccessGrant;
use App\Modules\Billing\Models\EventPackage;
use App\Modules\Billing\Models\EventPurchase;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Events\Models\Event;
use App\Modules\Plans\Models\Plan;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

// ─── Billing ─────────────────────────────────────────────

it('returns current subscription (null when none)', function () {
    [$user, $organization] = $this->actingAsOwner();

    $response = $this->apiGet('/billing/subscription');

    $this->assertApiSuccess($response);
    // No subscription yet
    expect($response->json('data'))->toBeNull();
});

it('returns only active plans in the billing catalog', function () {
    [$user, $organization] = $this->actingAsOwner();

    $activePlan = Plan::create([
        'code' => 'starter',
        'name' => 'Starter',
        'audience' => 'b2b',
        'status' => 'active',
        'description' => 'Plano ativo para checkout.',
    ]);

    $activePlan->prices()->create([
        'billing_cycle' => 'monthly',
        'currency' => 'BRL',
        'amount_cents' => 9900,
        'is_default' => true,
    ]);

    $activePlan->features()->createMany([
        ['feature_key' => 'wall.enabled', 'feature_value' => 'true'],
        ['feature_key' => 'events.max_active', 'feature_value' => '5'],
    ]);

    $inactivePlan = Plan::create([
        'code' => 'legacy-hidden',
        'name' => 'Legacy Hidden',
        'audience' => 'b2b',
        'status' => 'inactive',
        'description' => 'Nao deve aparecer no catalogo autenticado.',
    ]);

    $inactivePlan->prices()->create([
        'billing_cycle' => 'monthly',
        'currency' => 'BRL',
        'amount_cents' => 4900,
        'is_default' => true,
    ]);

    $response = $this->apiGet('/plans');

    $this->assertApiSuccess($response);
    expect(collect($response->json('data'))->pluck('code')->all())
        ->toContain('starter')
        ->not->toContain('legacy-hidden');
    $response->assertJsonPath('data.0.prices.0.amount_cents', 9900);
    expect(collect($response->json('data.0.features'))->pluck('feature_key')->all())
        ->toContain('wall.enabled', 'events.max_active');
});

it('forbids billing endpoints for roles without billing permissions', function () {
    [$user, $organization] = $this->actingAsViewer();

    $response = $this->apiGet('/billing/subscription');

    $this->assertApiForbidden($response);
});

it('forbids plans catalog for roles without billing permissions', function () {
    [$user, $organization] = $this->actingAsViewer();

    $response = $this->apiGet('/plans');

    $this->assertApiForbidden($response);
});

it('returns current subscription using the real plan code and feature schema', function () {
    [$user, $organization] = $this->actingAsOwner();

    $plan = Plan::create([
        'code' => 'starter',
        'name' => 'Starter',
        'audience' => 'b2b',
        'status' => 'active',
    ]);

    $plan->features()->createMany([
        ['feature_key' => 'play.enabled', 'feature_value' => 'false'],
        ['feature_key' => 'wall.enabled', 'feature_value' => 'true'],
    ]);

    \App\Modules\Billing\Models\Subscription::create([
        'organization_id' => $organization->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'starts_at' => now(),
        'renews_at' => now()->addMonth(),
    ]);

    $response = $this->apiGet('/billing/subscription');

    $this->assertApiSuccess($response);
    expect($response->json('data.plan_key'))->toBe('starter');
    expect($response->json('data.features')['wall.enabled'])->toBe('true');
});

it('creates subscription via checkout', function () {
    [$user, $organization] = $this->actingAsOwner();

    // Create a plan using the real schema (code + name are required)
    $plan = Plan::create([
        'code' => 'pro-parceiro',
        'name' => 'Pro Parceiro',
        'audience' => 'b2b',
        'status' => 'active',
    ]);

    $plan->prices()->create([
        'billing_cycle' => 'monthly',
        'currency' => 'BRL',
        'amount_cents' => 9900,
        'is_default' => true,
    ]);

    $response = $this->apiPost('/billing/checkout', [
        'plan_id' => $plan->id,
        'billing_cycle' => 'monthly',
    ]);

    $this->assertApiSuccess($response, 201);

    $response->assertJsonStructure([
        'data' => [
            'subscription_id',
            'plan_name',
            'status',
            'starts_at',
            'renews_at',
            'billing_order_id',
            'payment_id',
            'invoice_id',
        ],
    ]);

    expect($response->json('data.plan_name'))->toBe('Pro Parceiro');
    expect($response->json('data.status'))->toBe('active');

    $billingOrderId = $response->json('data.billing_order_id');
    $paymentId = $response->json('data.payment_id');
    $invoiceId = $response->json('data.invoice_id');

    $this->assertDatabaseHas('billing_orders', [
        'id' => $billingOrderId,
        'organization_id' => $organization->id,
        'buyer_user_id' => $user->id,
        'mode' => 'subscription',
        'status' => 'paid',
    ]);

    $this->assertDatabaseHas('payments', [
        'id' => $paymentId,
        'billing_order_id' => $billingOrderId,
        'status' => 'paid',
    ]);

    $this->assertDatabaseHas('invoices', [
        'id' => $invoiceId,
        'organization_id' => $organization->id,
        'billing_order_id' => $billingOrderId,
        'status' => 'paid',
    ]);
});

it('recalculates organization event entitlements automatically after subscription checkout', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'commercial_mode' => 'none',
    ]);

    $plan = Plan::create([
        'code' => 'pro-parceiro',
        'name' => 'Pro Parceiro',
        'audience' => 'b2b',
        'status' => 'active',
    ]);

    $plan->prices()->create([
        'billing_cycle' => 'monthly',
        'currency' => 'BRL',
        'amount_cents' => 14900,
        'is_default' => true,
    ]);

    $plan->features()->createMany([
        ['feature_key' => 'wall.enabled', 'feature_value' => 'true'],
        ['feature_key' => 'play.enabled', 'feature_value' => 'true'],
        ['feature_key' => 'media.retention_days', 'feature_value' => '120'],
    ]);

    $response = $this->apiPost('/billing/checkout', [
        'plan_id' => $plan->id,
        'billing_cycle' => 'monthly',
    ]);

    $this->assertApiSuccess($response, 201);

    $event->refresh();

    expect($event->commercial_mode?->value)->toBe('subscription_covered');
    expect($event->current_entitlements_json['modules']['wall'] ?? null)->toBeTrue();
    expect($event->current_entitlements_json['modules']['play'] ?? null)->toBeTrue();
    expect($event->current_entitlements_json['limits']['retention_days'] ?? null)->toBe(120);
});

it('returns current plan after checkout', function () {
    [$user, $organization] = $this->actingAsOwner();

    $plan = Plan::create([
        'code' => 'pro-parceiro',
        'name' => 'Pro Parceiro',
        'audience' => 'b2b',
        'status' => 'active',
    ]);

    // Create subscription directly for deterministic test
    \App\Modules\Billing\Models\Subscription::create([
        'organization_id' => $organization->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'starts_at' => now(),
        'renews_at' => now()->addMonth(),
    ]);

    $response = $this->apiGet('/plans/current');

    $this->assertApiSuccess($response);
});

it('cancels the current subscription of the organization at period end by default', function () {
    [$user, $organization] = $this->actingAsOwner();

    $plan = Plan::create([
        'code' => 'pro-parceiro',
        'name' => 'Pro Parceiro',
        'audience' => 'b2b',
        'status' => 'active',
    ]);

    $subscription = Subscription::create([
        'organization_id' => $organization->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'starts_at' => now()->subDays(5),
        'renews_at' => now()->addMonth(),
    ]);

    $response = $this->apiPost('/billing/subscription/cancel', [
        'reason' => 'Encerrar conta apos migracao.',
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.message', 'Assinatura da conta agendada para cancelamento ao fim do ciclo.');
    $response->assertJsonPath('data.subscription.id', $subscription->id);
    $response->assertJsonPath('data.subscription.status', 'canceled');
    $response->assertJsonPath('data.cancel_effective', 'period_end');
    $response->assertJsonPath('data.subscription.cancel_at_period_end', true);
    $response->assertJsonPath('data.subscription.cancellation_effective_at', $subscription->renews_at?->toISOString());

    $subscription->refresh();

    expect($subscription->status)->toBe('canceled');
    expect($subscription->canceled_at)->not()->toBeNull();
    expect($subscription->renews_at)->not()->toBeNull();
    expect($subscription->ends_at)->not()->toBeNull();
    expect($subscription->ends_at?->equalTo($subscription->renews_at))->toBeTrue();
});

it('allows financeiro role to cancel the current subscription at period end', function () {
    $this->seedPermissions();

    $organization = $this->createOrganization();
    $user = $this->createUser();

    \App\Modules\Organizations\Models\OrganizationMember::create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role_key' => 'financeiro',
        'is_owner' => false,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $user->assignRole('financeiro');
    $this->actingAs($user);

    $plan = Plan::create([
        'code' => 'starter',
        'name' => 'Starter',
        'audience' => 'b2b',
        'status' => 'active',
    ]);

    Subscription::create([
        'organization_id' => $organization->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'starts_at' => now()->subWeek(),
        'renews_at' => now()->addMonth(),
    ]);

    $response = $this->apiPost('/billing/subscription/cancel', [
        'reason' => 'Financeiro encerrou a renovacao.',
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.cancel_effective', 'period_end');
    $response->assertJsonPath('data.subscription.cancel_at_period_end', true);
});

it('keeps subscription-covered event entitlements until the period end when cancellation is scheduled', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'commercial_mode' => 'none',
    ]);

    $plan = Plan::create([
        'code' => 'pro-parceiro',
        'name' => 'Pro Parceiro',
        'audience' => 'b2b',
        'status' => 'active',
    ]);

    $plan->features()->createMany([
        ['feature_key' => 'wall.enabled', 'feature_value' => 'true'],
        ['feature_key' => 'play.enabled', 'feature_value' => 'true'],
        ['feature_key' => 'media.retention_days', 'feature_value' => '120'],
    ]);

    Subscription::create([
        'organization_id' => $organization->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'starts_at' => now()->subDays(5),
        'renews_at' => now()->addMonth(),
    ]);

    $event->refresh();

    expect($event->commercial_mode?->value)->toBe('subscription_covered');
    expect($event->current_entitlements_json['modules']['wall'] ?? null)->toBeTrue();
    expect($event->current_entitlements_json['modules']['play'] ?? null)->toBeTrue();

    $response = $this->apiPost('/billing/subscription/cancel', [
        'reason' => 'Cliente solicitou encerramento.',
    ]);

    $this->assertApiSuccess($response);

    $event->refresh();

    expect($event->commercial_mode?->value)->toBe('subscription_covered');
    expect($event->current_entitlements_json['commercial_mode'] ?? null)->toBe('subscription_covered');
    expect($event->current_entitlements_json['modules']['wall'] ?? null)->toBeTrue();
    expect($event->current_entitlements_json['modules']['play'] ?? null)->toBeTrue();
});

it('can cancel the current subscription immediately and drop subscription-covered event entitlements', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'commercial_mode' => 'none',
    ]);

    $plan = Plan::create([
        'code' => 'pro-parceiro',
        'name' => 'Pro Parceiro',
        'audience' => 'b2b',
        'status' => 'active',
    ]);

    $plan->features()->createMany([
        ['feature_key' => 'wall.enabled', 'feature_value' => 'true'],
        ['feature_key' => 'play.enabled', 'feature_value' => 'true'],
    ]);

    Subscription::create([
        'organization_id' => $organization->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'starts_at' => now()->subDays(5),
        'renews_at' => now()->addMonth(),
    ]);

    $event->refresh();

    expect($event->commercial_mode?->value)->toBe('subscription_covered');

    $response = $this->apiPost('/billing/subscription/cancel', [
        'effective' => 'immediately',
        'reason' => 'Encerramento imediato solicitado.',
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.message', 'Assinatura da conta cancelada com efeito imediato.');
    $response->assertJsonPath('data.cancel_effective', 'immediately');

    $event->refresh();

    expect($event->commercial_mode?->value)->toBe('none');
    expect($event->current_entitlements_json['commercial_mode'] ?? null)->toBe('none');
    expect($event->current_entitlements_json['modules']['wall'] ?? null)->toBeFalse();
    expect($event->current_entitlements_json['modules']['play'] ?? null)->toBeFalse();
});

it('cancels the current pagarme subscription immediately and syncs DELETE /subscriptions/{id} before closing local access', function () {
    [$user, $organization] = $this->actingAsOwner();

    config()->set('billing.gateways.subscription', 'pagarme');
    config()->set('services.pagarme', [
        'base_url' => 'https://api.pagar.me/core/v5/',
        'secret_key' => 'sk_test_7611662845434f72bdb0986b69d54ce1',
        'public_key' => 'pk_test_jGWvy7PhpBukl396',
        'timeout' => 15,
        'connect_timeout' => 5,
        'retry_times' => 1,
        'retry_sleep_ms' => 0,
    ]);

    Http::preventStrayRequests();

    Http::fake([
        'https://api.pagar.me/core/v5/subscriptions/sub_cancel_123' => Http::response([
            'id' => 'sub_cancel_123',
            'status' => 'canceled',
        ], 200),
    ]);

    $plan = Plan::create([
        'code' => 'pro-parceiro',
        'name' => 'Pro Parceiro',
        'audience' => 'b2b',
        'status' => 'active',
    ]);

    $subscription = Subscription::create([
        'organization_id' => $organization->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'payment_method' => 'credit_card',
        'starts_at' => now()->subDays(5),
        'renews_at' => now()->addMonth(),
        'gateway_provider' => 'pagarme',
        'gateway_customer_id' => 'cus_cancel_123',
        'gateway_subscription_id' => 'sub_cancel_123',
        'contract_status' => 'active',
        'billing_status' => 'paid',
        'access_status' => 'enabled',
    ]);

    $response = $this->apiPost('/billing/subscription/cancel', [
        'effective' => 'immediately',
        'reason' => 'Encerramento imediato sincronizado com o gateway.',
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.message', 'Assinatura da conta cancelada com efeito imediato.');
    $response->assertJsonPath('data.cancel_effective', 'immediately');
    $response->assertJsonPath('data.subscription.id', $subscription->id);
    $response->assertJsonPath('data.subscription.status', 'canceled');
    $response->assertJsonPath('data.subscription.access_status', 'disabled');

    $subscription->refresh();

    expect($subscription->status)->toBe('canceled')
        ->and($subscription->contract_status)->toBe('canceled')
        ->and($subscription->access_status)->toBe('disabled')
        ->and($subscription->renews_at)->toBeNull()
        ->and($subscription->canceled_at)->not()->toBeNull();

    Http::assertSent(function (HttpRequest $request) {
        return $request->method() === 'DELETE'
            && $request->url() === 'https://api.pagar.me/core/v5/subscriptions/sub_cancel_123';
    });
});

it('validates cancellation when the organization has no subscription', function () {
    [$user, $organization] = $this->actingAsOwner();

    $response = $this->apiPost('/billing/subscription/cancel', []);

    $this->assertApiValidationError($response, ['subscription']);
});

it('returns billing history from real invoices instead of event purchases', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $package = EventPackage::factory()->create([
        'code' => 'interactive-event',
        'name' => 'Interactive Event',
    ]);

    $order = BillingOrder::create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'buyer_user_id' => $user->id,
        'mode' => 'event_package',
        'status' => 'paid',
        'currency' => 'BRL',
        'total_cents' => 19900,
        'gateway_provider' => 'manual',
        'confirmed_at' => now(),
        'metadata_json' => [
            'journey' => 'test_invoice_history',
        ],
    ]);

    $order->items()->create([
        'item_type' => 'event_package',
        'reference_id' => $package->id,
        'description' => 'Pacote Interactive Event',
        'quantity' => 1,
        'unit_amount_cents' => 19900,
        'total_amount_cents' => 19900,
        'snapshot_json' => [
            'package' => [
                'id' => $package->id,
                'code' => 'interactive-event',
                'name' => 'Interactive Event',
                'description' => null,
                'target_audience' => 'both',
            ],
        ],
    ]);

    Payment::create([
        'billing_order_id' => $order->id,
        'status' => PaymentStatus::Paid->value,
        'amount_cents' => 19900,
        'currency' => 'BRL',
        'gateway_provider' => 'manual',
        'gateway_payment_id' => 'pay_test_123',
        'paid_at' => now(),
        'raw_payload_json' => ['source' => 'test'],
    ]);

    Invoice::create([
        'organization_id' => $organization->id,
        'billing_order_id' => $order->id,
        'invoice_number' => 'EVV-TEST-000001',
        'status' => InvoiceStatus::Paid->value,
        'amount_cents' => 19900,
        'currency' => 'BRL',
        'issued_at' => now(),
        'due_at' => now(),
        'paid_at' => now(),
        'snapshot_json' => [
            'package' => [
                'id' => $package->id,
                'code' => 'interactive-event',
                'name' => 'Interactive Event',
            ],
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
            ],
        ],
    ]);

    $response = $this->apiGet('/billing/invoices');

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.0.invoice_number', 'EVV-TEST-000001');
    $response->assertJsonPath('data.0.package.code', 'interactive-event');
    $response->assertJsonPath('data.0.plan', null);
    $response->assertJsonPath('data.0.order.mode', 'event_package');
    $response->assertJsonPath('data.0.payment.status', 'paid');
    $response->assertJsonPath('meta.page', 1);
    $response->assertJsonPath('meta.total', 1);
});

it('cancels a pending event-package billing order through the configured gateway', function () {
    [$user, $organization] = $this->actingAsOwner();

    config()->set('billing.gateways.event_package', 'pagarme');
    config()->set('services.pagarme', [
        'base_url' => 'https://api.pagar.me/core/v5/',
        'secret_key' => 'sk_test_7611662845434f72bdb0986b69d54ce1',
        'public_key' => 'pk_test_jGWvy7PhpBukl396',
        'timeout' => 15,
        'connect_timeout' => 5,
        'retry_times' => 1,
        'retry_sleep_ms' => 0,
    ]);

    Http::preventStrayRequests();

    Http::fake([
        'https://api.pagar.me/core/v5/charges/ch_cancel_order_123' => Http::response([
            'id' => 'ch_cancel_order_123',
            'status' => 'canceled',
            'last_transaction' => [
                'id' => 'tx_cancel_order_123',
            ],
        ], 200),
    ]);

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $package = EventPackage::factory()->create();

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
        'gateway_order_id' => 'or_cancel_order_123',
        'gateway_charge_id' => 'ch_cancel_order_123',
        'gateway_status' => 'pending',
        'metadata_json' => [
            'journey' => 'test_cancel_pending_order',
        ],
    ]);

    $order->items()->create([
        'item_type' => 'event_package',
        'reference_id' => $package->id,
        'description' => 'Pacote de teste',
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

    $response = $this->apiPost("/billing/orders/{$order->uuid}/cancel", [
        'reason' => 'Cliente desistiu antes do pagamento.',
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.order.uuid', $order->uuid);
    $response->assertJsonPath('data.order.status', 'canceled');
    $response->assertJsonPath('data.order.gateway_charge_id', 'ch_cancel_order_123');
    $response->assertJsonPath('data.order.gateway_transaction_id', 'tx_cancel_order_123');

    $this->assertDatabaseHas('billing_orders', [
        'id' => $order->id,
        'status' => 'canceled',
        'gateway_provider' => 'pagarme',
        'gateway_order_id' => 'or_cancel_order_123',
        'gateway_charge_id' => 'ch_cancel_order_123',
        'gateway_transaction_id' => 'tx_cancel_order_123',
        'gateway_status' => 'canceled',
    ]);

    $order->refresh();
    expect($order->canceled_at)->not()->toBeNull();

    Http::assertSent(function (HttpRequest $request) {
        return $request->method() === 'DELETE'
            && $request->url() === 'https://api.pagar.me/core/v5/charges/ch_cancel_order_123';
    });
});

it('refunds a paid event-package billing order through the configured gateway and revokes access', function () {
    [$user, $organization] = $this->actingAsOwner();

    config()->set('billing.gateways.event_package', 'pagarme');
    config()->set('services.pagarme', [
        'base_url' => 'https://api.pagar.me/core/v5/',
        'secret_key' => 'sk_test_7611662845434f72bdb0986b69d54ce1',
        'public_key' => 'pk_test_jGWvy7PhpBukl396',
        'timeout' => 15,
        'connect_timeout' => 5,
        'retry_times' => 1,
        'retry_sleep_ms' => 0,
    ]);

    Http::preventStrayRequests();

    Http::fake([
        'https://api.pagar.me/core/v5/charges/ch_refund_order_123' => Http::response([
            'id' => 'ch_refund_order_123',
            'status' => 'refunded',
            'last_transaction' => [
                'id' => 'tx_refund_order_123',
            ],
        ], 200),
    ]);

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $package = EventPackage::factory()->create();

    $order = BillingOrder::create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'buyer_user_id' => $user->id,
        'mode' => 'event_package',
        'status' => 'paid',
        'currency' => 'BRL',
        'total_cents' => 19900,
        'payment_method' => 'credit_card',
        'gateway_provider' => 'pagarme',
        'gateway_order_id' => 'or_refund_order_123',
        'gateway_charge_id' => 'ch_refund_order_123',
        'gateway_status' => 'paid',
        'confirmed_at' => now()->subMinute(),
        'paid_at' => now()->subMinute(),
        'metadata_json' => [
            'journey' => 'test_refund_paid_order',
        ],
    ]);

    $order->items()->create([
        'item_type' => 'event_package',
        'reference_id' => $package->id,
        'description' => 'Pacote de teste',
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

    Payment::create([
        'billing_order_id' => $order->id,
        'status' => PaymentStatus::Paid->value,
        'amount_cents' => 19900,
        'currency' => 'BRL',
        'payment_method' => 'credit_card',
        'gateway_provider' => 'pagarme',
        'gateway_payment_id' => 'ch_refund_order_123',
        'gateway_order_id' => 'or_refund_order_123',
        'gateway_charge_id' => 'ch_refund_order_123',
        'gateway_status' => 'paid',
        'paid_at' => now()->subMinute(),
        'raw_payload_json' => ['source' => 'test'],
    ]);

    Invoice::create([
        'organization_id' => $organization->id,
        'billing_order_id' => $order->id,
        'invoice_number' => 'EVV-TEST-REFUND-000001',
        'status' => InvoiceStatus::Paid->value,
        'amount_cents' => 19900,
        'currency' => 'BRL',
        'issued_at' => now()->subMinute(),
        'due_at' => now()->subMinute(),
        'paid_at' => now()->subMinute(),
        'snapshot_json' => [],
    ]);

    $purchase = EventPurchase::create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'billing_order_id' => $order->id,
        'package_id' => $package->id,
        'price_snapshot_cents' => 19900,
        'currency' => 'BRL',
        'features_snapshot_json' => [],
        'status' => 'paid',
        'purchased_by_user_id' => $user->id,
        'purchased_at' => now()->subMinute(),
    ]);

    EventAccessGrant::create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'source_type' => EventAccessGrantSourceType::EventPurchase->value,
        'source_id' => $purchase->id,
        'package_id' => $package->id,
        'status' => EventAccessGrantStatus::Active->value,
        'priority' => EventAccessGrantSourceType::EventPurchase->defaultPriority(),
        'merge_strategy' => 'replace',
        'starts_at' => now()->subMinute(),
        'ends_at' => null,
        'features_snapshot_json' => [],
        'limits_snapshot_json' => [],
        'granted_by_user_id' => $user->id,
        'notes' => 'Grant de teste',
        'metadata_json' => [],
    ]);

    $response = $this->apiPost("/billing/orders/{$order->uuid}/cancel", [
        'reason' => 'Cliente solicitou estorno.',
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.order.uuid', $order->uuid);
    $response->assertJsonPath('data.order.status', 'refunded');
    $response->assertJsonPath('data.order.gateway_charge_id', 'ch_refund_order_123');
    $response->assertJsonPath('data.order.gateway_transaction_id', 'tx_refund_order_123');
    $response->assertJsonPath('data.payment.status', 'refunded');

    $this->assertDatabaseHas('billing_orders', [
        'id' => $order->id,
        'status' => 'refunded',
        'gateway_provider' => 'pagarme',
        'gateway_order_id' => 'or_refund_order_123',
        'gateway_charge_id' => 'ch_refund_order_123',
        'gateway_transaction_id' => 'tx_refund_order_123',
        'gateway_status' => 'refunded',
    ]);

    $this->assertDatabaseHas('payments', [
        'billing_order_id' => $order->id,
        'status' => 'refunded',
        'gateway_charge_id' => 'ch_refund_order_123',
        'gateway_transaction_id' => 'tx_refund_order_123',
        'gateway_status' => 'refunded',
    ]);

    $this->assertDatabaseHas('invoices', [
        'billing_order_id' => $order->id,
        'status' => 'refunded',
    ]);

    $this->assertDatabaseHas('event_purchases', [
        'id' => $purchase->id,
        'status' => 'refunded',
    ]);

    $this->assertDatabaseHas('event_access_grants', [
        'event_id' => $event->id,
        'source_type' => EventAccessGrantSourceType::EventPurchase->value,
        'source_id' => $purchase->id,
        'status' => EventAccessGrantStatus::Revoked->value,
    ]);

    Http::assertSent(function (HttpRequest $request) {
        return $request->method() === 'DELETE'
            && $request->url() === 'https://api.pagar.me/core/v5/charges/ch_refund_order_123';
    });
});

it('retries a pagarme event-package checkout with the same idempotency key when the gateway order is still missing', function () {
    [$user, $organization] = $this->actingAsOwner();

    config()->set('billing.gateways.event_package', 'pagarme');
    config()->set('services.pagarme', [
        'base_url' => 'https://api.pagar.me/core/v5/',
        'secret_key' => 'sk_test_7611662845434f72bdb0986b69d54ce1',
        'public_key' => 'pk_test_jGWvy7PhpBukl396',
        'statement_descriptor' => 'EVENTOVIVO',
        'pix_expires_in' => 1800,
        'timeout' => 15,
        'connect_timeout' => 5,
        'retry_times' => 1,
        'retry_sleep_ms' => 0,
    ]);

    Http::preventStrayRequests();

    Http::fake([
        'https://api.pagar.me/core/v5/orders' => Http::response([
            'id' => 'or_retry_checkout_123',
            'status' => 'pending',
            'charges' => [
                [
                    'id' => 'ch_retry_checkout_123',
                    'status' => 'pending',
                    'payment_method' => 'pix',
                    'last_transaction' => [
                        'id' => 'tx_retry_checkout_123',
                        'status' => 'pending',
                        'qr_code' => '000201010212retry',
                        'qr_code_url' => 'https://api.pagar.me/core/v5/transactions/tx_retry_checkout_123/qrcode?payment_method=pix',
                        'expires_at' => '2026-04-05T14:00:00Z',
                    ],
                ],
            ],
        ], 200),
    ]);

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $package = EventPackage::factory()->create([
        'code' => 'retry-checkout-package',
        'name' => 'Retry Checkout Package',
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
        'gateway_status' => 'pending_payment',
        'metadata_json' => [
            'journey' => 'public_event_checkout',
            'package_id' => $package->id,
            'package_code' => $package->code,
            'payment' => [
                'method' => 'pix',
                'pix' => [
                    'expires_in' => 1800,
                ],
            ],
        ],
    ]);

    $order->update([
        'idempotency_key' => "billing-order:{$order->uuid}:attempt:1",
    ]);

    $order->items()->create([
        'item_type' => 'event_package',
        'reference_id' => $package->id,
        'description' => 'Pacote de teste retry',
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

    $response = $this->apiPost("/billing/orders/{$order->uuid}/retry", []);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.retry.action', 'gateway_checkout_retried');
    $response->assertJsonPath('data.retry.external_call', true);
    $response->assertJsonPath('data.retry.idempotency_key', "billing-order:{$order->uuid}:attempt:1");
    $response->assertJsonPath('data.order.gateway_order_id', 'or_retry_checkout_123');
    $response->assertJsonPath('data.order.gateway_charge_id', 'ch_retry_checkout_123');
    $response->assertJsonPath('data.order.gateway_transaction_id', 'tx_retry_checkout_123');

    $this->assertDatabaseHas('billing_orders', [
        'id' => $order->id,
        'gateway_order_id' => 'or_retry_checkout_123',
        'gateway_charge_id' => 'ch_retry_checkout_123',
        'gateway_transaction_id' => 'tx_retry_checkout_123',
        'gateway_status' => 'pending_payment',
        'idempotency_key' => "billing-order:{$order->uuid}:attempt:1",
    ]);

    Http::assertSent(function (HttpRequest $request) use ($order) {
        return $request->method() === 'POST'
            && $request->url() === 'https://api.pagar.me/core/v5/orders'
            && ($request->header('Idempotency-Key')[0] ?? null) === "billing-order:{$order->uuid}:attempt:1"
            && data_get($request->data(), 'metadata.billing_order_uuid') === $order->uuid;
    });
});

it('skips the external retry when the pagarme billing order already has a gateway snapshot', function () {
    [$user, $organization] = $this->actingAsOwner();

    config()->set('billing.gateways.event_package', 'pagarme');
    config()->set('services.pagarme', [
        'base_url' => 'https://api.pagar.me/core/v5/',
        'secret_key' => 'sk_test_7611662845434f72bdb0986b69d54ce1',
        'public_key' => 'pk_test_jGWvy7PhpBukl396',
        'timeout' => 15,
        'connect_timeout' => 5,
        'retry_times' => 1,
        'retry_sleep_ms' => 0,
    ]);

    Http::preventStrayRequests();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $package = EventPackage::factory()->create([
        'code' => 'retry-skip-package',
        'name' => 'Retry Skip Package',
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
        'gateway_order_id' => 'or_existing_retry_123',
        'gateway_charge_id' => 'ch_existing_retry_123',
        'gateway_transaction_id' => 'tx_existing_retry_123',
        'gateway_status' => 'pending_payment',
        'idempotency_key' => 'billing-order:existing-retry:attempt:1',
        'metadata_json' => [
            'journey' => 'public_event_checkout',
            'package_id' => $package->id,
            'package_code' => $package->code,
            'gateway' => [
                'provider_key' => 'pagarme',
                'gateway_order_id' => 'or_existing_retry_123',
                'gateway_charge_id' => 'ch_existing_retry_123',
                'gateway_transaction_id' => 'tx_existing_retry_123',
                'status' => 'pending_payment',
            ],
        ],
    ]);

    $order->items()->create([
        'item_type' => 'event_package',
        'reference_id' => $package->id,
        'description' => 'Pacote de teste retry skip',
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

    $response = $this->apiPost("/billing/orders/{$order->uuid}/retry", []);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.retry.action', 'skipped_existing_gateway_snapshot');
    $response->assertJsonPath('data.retry.external_call', false);
    $response->assertJsonPath('data.retry.idempotency_key', 'billing-order:existing-retry:attempt:1');
    $response->assertJsonPath('data.order.gateway_order_id', 'or_existing_retry_123');
    $response->assertJsonPath('data.order.gateway_charge_id', 'ch_existing_retry_123');
});

it('rejects a billing-order retry when another checkout attempt is already locked for the same order', function () {
    [$user, $organization] = $this->actingAsOwner();

    config()->set('billing.gateways.event_package', 'pagarme');
    config()->set('cache.default', 'array');
    config()->set('services.pagarme', [
        'base_url' => 'https://api.pagar.me/core/v5/',
        'secret_key' => 'sk_test_7611662845434f72bdb0986b69d54ce1',
        'public_key' => 'pk_test_jGWvy7PhpBukl396',
        'timeout' => 15,
        'connect_timeout' => 5,
        'retry_times' => 1,
        'retry_sleep_ms' => 0,
    ]);

    Http::preventStrayRequests();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $package = EventPackage::factory()->create([
        'code' => 'retry-lock-package',
        'name' => 'Retry Lock Package',
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
        'gateway_status' => 'pending_payment',
        'metadata_json' => [
            'journey' => 'public_event_checkout',
            'package_id' => $package->id,
            'package_code' => $package->code,
            'payment' => [
                'method' => 'pix',
            ],
        ],
    ]);

    $order->items()->create([
        'item_type' => 'event_package',
        'reference_id' => $package->id,
        'description' => 'Pacote de teste retry lock',
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

    $lock = Cache::lock("billing-order-gateway-checkout:{$order->id}", 30);

    expect($lock->get())->toBeTrue();

    $response = $this->apiPost("/billing/orders/{$order->uuid}/retry", []);

    $this->assertApiValidationError($response, ['billing_order']);

    $lock->release();
});

it('refreshes a pagarme event-package order from gateway snapshots and reconciles it as paid', function () {
    [$user, $organization] = $this->actingAsOwner();

    config()->set('billing.gateways.event_package', 'pagarme');
    config()->set('services.pagarme', [
        'base_url' => 'https://api.pagar.me/core/v5/',
        'secret_key' => 'sk_test_7611662845434f72bdb0986b69d54ce1',
        'public_key' => 'pk_test_jGWvy7PhpBukl396',
        'timeout' => 15,
        'connect_timeout' => 5,
        'retry_times' => 1,
        'retry_sleep_ms' => 0,
    ]);

    Http::preventStrayRequests();

    Http::fake([
        'https://api.pagar.me/core/v5/orders/or_refresh_paid_123' => Http::response([
            'id' => 'or_refresh_paid_123',
            'status' => 'paid',
            'charges' => [
                [
                    'id' => 'ch_refresh_paid_123',
                    'status' => 'paid',
                    'payment_method' => 'credit_card',
                    'last_transaction' => [
                        'id' => 'tx_refresh_paid_123',
                        'status' => 'paid',
                        'acquirer_message' => 'Transacao aprovada',
                        'acquirer_return_code' => '00',
                    ],
                ],
            ],
        ], 200),
        'https://api.pagar.me/core/v5/charges/ch_refresh_paid_123' => Http::response([
            'id' => 'ch_refresh_paid_123',
            'status' => 'paid',
            'payment_method' => 'credit_card',
            'last_transaction' => [
                'id' => 'tx_refresh_paid_123',
                'status' => 'paid',
                'acquirer_message' => 'Transacao aprovada',
                'acquirer_return_code' => '00',
            ],
            'order' => [
                'id' => 'or_refresh_paid_123',
            ],
        ], 200),
    ]);

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'commercial_mode' => 'none',
    ]);

    $package = EventPackage::factory()->create([
        'code' => 'refresh-paid-package',
        'name' => 'Refresh Paid Package',
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
        'gateway_order_id' => 'or_refresh_paid_123',
        'gateway_charge_id' => 'ch_refresh_paid_123',
        'gateway_status' => 'processing',
        'metadata_json' => [
            'journey' => 'public_event_checkout',
            'package_id' => $package->id,
            'package_code' => $package->code,
        ],
    ]);

    $order->items()->create([
        'item_type' => 'event_package',
        'reference_id' => $package->id,
        'description' => 'Pacote de teste refresh paid',
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

    $response = $this->apiPost("/billing/orders/{$order->uuid}/refresh", []);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.sync.action', 'payment_registered');
    $response->assertJsonPath('data.sync.transition_applied', true);
    $response->assertJsonPath('data.order.status', 'paid');
    $response->assertJsonPath('data.order.gateway_charge_id', 'ch_refresh_paid_123');
    $response->assertJsonPath('data.gateway.order.id', 'or_refresh_paid_123');
    $response->assertJsonPath('data.gateway.charge.id', 'ch_refresh_paid_123');

    $this->assertDatabaseHas('billing_orders', [
        'id' => $order->id,
        'status' => 'paid',
        'gateway_order_id' => 'or_refresh_paid_123',
        'gateway_charge_id' => 'ch_refresh_paid_123',
        'gateway_transaction_id' => 'tx_refresh_paid_123',
        'gateway_status' => 'paid',
    ]);

    $this->assertDatabaseHas('payments', [
        'billing_order_id' => $order->id,
        'status' => 'paid',
        'gateway_charge_id' => 'ch_refresh_paid_123',
        'gateway_transaction_id' => 'tx_refresh_paid_123',
        'gateway_status' => 'paid',
        'acquirer_message' => 'Transacao aprovada',
        'acquirer_return_code' => '00',
    ]);

    $this->assertDatabaseHas('event_purchases', [
        'billing_order_id' => $order->id,
        'package_id' => $package->id,
        'status' => 'paid',
    ]);

    Http::assertSent(function (HttpRequest $request) {
        return $request->method() === 'GET'
            && $request->url() === 'https://api.pagar.me/core/v5/orders/or_refresh_paid_123';
    });

    Http::assertSent(function (HttpRequest $request) {
        return $request->method() === 'GET'
            && $request->url() === 'https://api.pagar.me/core/v5/charges/ch_refresh_paid_123';
    });
});

it('refreshes a pagarme event-package order and keeps it pending when the remote charge is processing', function () {
    [$user, $organization] = $this->actingAsOwner();

    config()->set('billing.gateways.event_package', 'pagarme');
    config()->set('services.pagarme', [
        'base_url' => 'https://api.pagar.me/core/v5/',
        'secret_key' => 'sk_test_7611662845434f72bdb0986b69d54ce1',
        'public_key' => 'pk_test_jGWvy7PhpBukl396',
        'timeout' => 15,
        'connect_timeout' => 5,
        'retry_times' => 1,
        'retry_sleep_ms' => 0,
    ]);

    Http::preventStrayRequests();

    Http::fake([
        'https://api.pagar.me/core/v5/orders/or_refresh_processing_123' => Http::response([
            'id' => 'or_refresh_processing_123',
            'status' => 'pending',
            'charges' => [
                [
                    'id' => 'ch_refresh_processing_123',
                    'status' => 'processing',
                    'payment_method' => 'credit_card',
                    'last_transaction' => [
                        'id' => 'tx_refresh_processing_123',
                        'status' => 'processing',
                    ],
                ],
            ],
        ], 200),
        'https://api.pagar.me/core/v5/charges/ch_refresh_processing_123' => Http::response([
            'id' => 'ch_refresh_processing_123',
            'status' => 'processing',
            'payment_method' => 'credit_card',
            'last_transaction' => [
                'id' => 'tx_refresh_processing_123',
                'status' => 'processing',
            ],
            'order' => [
                'id' => 'or_refresh_processing_123',
            ],
        ], 200),
    ]);

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $package = EventPackage::factory()->create();

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
        'gateway_order_id' => 'or_refresh_processing_123',
        'gateway_charge_id' => 'ch_refresh_processing_123',
        'gateway_status' => 'pending',
        'metadata_json' => [
            'journey' => 'public_event_checkout',
            'package_id' => $package->id,
            'package_code' => $package->code,
        ],
    ]);

    $order->items()->create([
        'item_type' => 'event_package',
        'reference_id' => $package->id,
        'description' => 'Pacote de teste refresh processing',
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

    $response = $this->apiPost("/billing/orders/{$order->uuid}/refresh", []);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.sync.action', 'snapshot_refreshed');
    $response->assertJsonPath('data.sync.transition_applied', false);
    $response->assertJsonPath('data.order.status', 'pending_payment');
    $response->assertJsonPath('data.order.gateway_status', 'processing');
    $response->assertJsonPath('data.gateway.charge.status', 'processing');

    $this->assertDatabaseHas('billing_orders', [
        'id' => $order->id,
        'status' => 'pending_payment',
        'gateway_transaction_id' => 'tx_refresh_processing_123',
        'gateway_status' => 'processing',
    ]);

    $this->assertDatabaseMissing('event_purchases', [
        'billing_order_id' => $order->id,
    ]);
});

it('refreshes a paid pagarme event-package order and reconciles a chargeback from the gateway charge snapshot', function () {
    [$user, $organization] = $this->actingAsOwner();

    config()->set('billing.gateways.event_package', 'pagarme');
    config()->set('services.pagarme', [
        'base_url' => 'https://api.pagar.me/core/v5/',
        'secret_key' => 'sk_test_7611662845434f72bdb0986b69d54ce1',
        'public_key' => 'pk_test_jGWvy7PhpBukl396',
        'timeout' => 15,
        'connect_timeout' => 5,
        'retry_times' => 1,
        'retry_sleep_ms' => 0,
    ]);

    Http::preventStrayRequests();

    Http::fake([
        'https://api.pagar.me/core/v5/orders/or_refresh_chargeback_123' => Http::response([
            'id' => 'or_refresh_chargeback_123',
            'status' => 'paid',
            'charges' => [
                [
                    'id' => 'ch_refresh_chargeback_123',
                    'status' => 'chargedback',
                    'payment_method' => 'credit_card',
                    'last_transaction' => [
                        'id' => 'tx_refresh_chargeback_123',
                        'status' => 'chargedback',
                    ],
                ],
            ],
        ], 200),
        'https://api.pagar.me/core/v5/charges/ch_refresh_chargeback_123' => Http::response([
            'id' => 'ch_refresh_chargeback_123',
            'status' => 'chargedback',
            'payment_method' => 'credit_card',
            'last_transaction' => [
                'id' => 'tx_refresh_chargeback_123',
                'status' => 'chargedback',
            ],
            'order' => [
                'id' => 'or_refresh_chargeback_123',
            ],
        ], 200),
    ]);

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $package = EventPackage::factory()->create();

    $order = BillingOrder::create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'buyer_user_id' => $user->id,
        'mode' => 'event_package',
        'status' => 'paid',
        'currency' => 'BRL',
        'total_cents' => 19900,
        'payment_method' => 'credit_card',
        'gateway_provider' => 'pagarme',
        'gateway_order_id' => 'or_refresh_chargeback_123',
        'gateway_charge_id' => 'ch_refresh_chargeback_123',
        'gateway_transaction_id' => 'tx_paid_seed_123',
        'gateway_status' => 'paid',
        'confirmed_at' => now()->subMinute(),
        'paid_at' => now()->subMinute(),
        'metadata_json' => [
            'journey' => 'public_event_checkout',
            'package_id' => $package->id,
            'package_code' => $package->code,
        ],
    ]);

    $order->items()->create([
        'item_type' => 'event_package',
        'reference_id' => $package->id,
        'description' => 'Pacote de teste refresh chargeback',
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

    Payment::create([
        'billing_order_id' => $order->id,
        'status' => PaymentStatus::Paid->value,
        'amount_cents' => 19900,
        'currency' => 'BRL',
        'payment_method' => 'credit_card',
        'gateway_provider' => 'pagarme',
        'gateway_payment_id' => 'ch_refresh_chargeback_123',
        'gateway_order_id' => 'or_refresh_chargeback_123',
        'gateway_charge_id' => 'ch_refresh_chargeback_123',
        'gateway_transaction_id' => 'tx_paid_seed_123',
        'gateway_status' => 'paid',
        'paid_at' => now()->subMinute(),
        'raw_payload_json' => ['source' => 'seed_paid_payment'],
    ]);

    Invoice::create([
        'organization_id' => $organization->id,
        'billing_order_id' => $order->id,
        'invoice_number' => 'EVV-TEST-CHARGEBACK-000001',
        'status' => InvoiceStatus::Paid->value,
        'amount_cents' => 19900,
        'currency' => 'BRL',
        'issued_at' => now()->subMinute(),
        'due_at' => now()->subMinute(),
        'paid_at' => now()->subMinute(),
        'snapshot_json' => [],
    ]);

    $purchase = EventPurchase::create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'billing_order_id' => $order->id,
        'package_id' => $package->id,
        'price_snapshot_cents' => 19900,
        'currency' => 'BRL',
        'features_snapshot_json' => [],
        'status' => 'paid',
        'purchased_by_user_id' => $user->id,
        'purchased_at' => now()->subMinute(),
    ]);

    EventAccessGrant::create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'source_type' => EventAccessGrantSourceType::EventPurchase->value,
        'source_id' => $purchase->id,
        'package_id' => $package->id,
        'status' => EventAccessGrantStatus::Active->value,
        'priority' => EventAccessGrantSourceType::EventPurchase->defaultPriority(),
        'merge_strategy' => 'replace',
        'starts_at' => now()->subMinute(),
        'ends_at' => null,
        'features_snapshot_json' => [],
        'limits_snapshot_json' => [],
        'granted_by_user_id' => $user->id,
        'notes' => 'Grant de teste chargeback',
        'metadata_json' => [],
    ]);

    $response = $this->apiPost("/billing/orders/{$order->uuid}/refresh", []);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.sync.action', 'payment_refunded');
    $response->assertJsonPath('data.sync.transition_applied', true);
    $response->assertJsonPath('data.order.status', 'refunded');
    $response->assertJsonPath('data.order.gateway_status', 'chargedback');
    $response->assertJsonPath('data.payment.gateway_status', 'chargedback');

    $this->assertDatabaseHas('billing_orders', [
        'id' => $order->id,
        'status' => 'refunded',
        'gateway_transaction_id' => 'tx_refresh_chargeback_123',
        'gateway_status' => 'chargedback',
    ]);

    $this->assertDatabaseHas('payments', [
        'billing_order_id' => $order->id,
        'status' => 'refunded',
        'gateway_transaction_id' => 'tx_refresh_chargeback_123',
        'gateway_status' => 'chargedback',
    ]);

    $this->assertDatabaseHas('event_purchases', [
        'id' => $purchase->id,
        'status' => 'chargedback',
    ]);

    $this->assertDatabaseHas('event_access_grants', [
        'event_id' => $event->id,
        'source_type' => EventAccessGrantSourceType::EventPurchase->value,
        'source_id' => $purchase->id,
        'status' => EventAccessGrantStatus::Revoked->value,
    ]);
});

it('validates checkout requires plan_id', function () {
    [$user, $organization] = $this->actingAsOwner();

    $response = $this->apiPost('/billing/checkout', []);

    $this->assertApiValidationError($response, ['plan_id']);
});

it('rejects billing access for unauthenticated user', function () {
    $response = $this->apiGet('/billing/subscription');

    $this->assertApiUnauthorized($response);
});
