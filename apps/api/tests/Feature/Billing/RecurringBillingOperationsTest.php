<?php

use App\Modules\Billing\Models\BillingProfile;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\SubscriptionCycle;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Plans\Models\Plan;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function configurePagarmeRecurringBillingForOpsTests(): void
{
    config()->set('billing.gateways.subscription', 'pagarme');
    config()->set('services.pagarme', [
        'base_url' => 'https://api.pagar.me/core/v5/',
        'secret_key' => 'sk_test_7611662845434f72bdb0986b69d54ce1',
        'public_key' => 'pk_test_jGWvy7PhpBukl396',
        'statement_descriptor' => 'EVENTOVIVO',
        'timeout' => 15,
        'connect_timeout' => 5,
        'retry_times' => 1,
        'retry_sleep_ms' => 0,
    ]);
}

function createRecurringOpsSubscription(Organization $organization, array $attributes = []): Subscription
{
    $plan = Plan::create([
        'code' => 'ops-recurring-plan',
        'name' => 'Ops Recorrente',
        'audience' => 'b2b',
        'status' => 'active',
    ]);

    return Subscription::create(array_merge([
        'organization_id' => $organization->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'payment_method' => 'credit_card',
        'starts_at' => now()->subDay(),
        'current_period_started_at' => now()->subDay(),
        'current_period_ends_at' => now()->addMonth(),
        'renews_at' => now()->addMonth(),
        'next_billing_at' => now()->addMonth(),
        'gateway_provider' => 'pagarme',
        'gateway_customer_id' => 'cus_ops_123',
        'gateway_plan_id' => 'plan_ops_123',
        'gateway_card_id' => 'card_old_123',
        'gateway_subscription_id' => 'sub_ops_123',
        'contract_status' => 'active',
        'billing_status' => 'paid',
        'access_status' => 'enabled',
    ], $attributes));
}

it('lists wallet cards from the canonical billing profile customer', function () {
    configurePagarmeRecurringBillingForOpsTests();
    [, $organization] = $this->actingAsOwner();
    createRecurringOpsSubscription($organization, [
        'gateway_customer_id' => null,
    ]);
    BillingProfile::factory()->create([
        'organization_id' => $organization->id,
        'gateway_customer_id' => 'cus_profile_123',
        'gateway_default_card_id' => 'card_wallet_default',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://api.pagar.me/core/v5/customers/cus_profile_123/cards' => Http::response([
            'data' => [[
                'id' => 'card_wallet_default',
                'brand' => 'visa',
                'holder_name' => 'CAMILA ROCHA',
                'last_four_digits' => '0010',
                'exp_month' => 12,
                'exp_year' => 2030,
                'status' => 'active',
            ]],
        ], 200),
    ]);

    $response = $this->apiGet('/billing/subscription/cards');

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.0.id', 'card_wallet_default');
    $response->assertJsonPath('data.0.is_default', true);
    $response->assertJsonPath('data.0.last_four', '0010');

    Http::assertSent(fn (Request $request) => $request->method() === 'GET'
        && $request->url() === 'https://api.pagar.me/core/v5/customers/cus_profile_123/cards');
});

it('updates the recurring subscription card using a saved wallet card id', function () {
    configurePagarmeRecurringBillingForOpsTests();
    [, $organization] = $this->actingAsOwner();
    $subscription = createRecurringOpsSubscription($organization);
    BillingProfile::factory()->create([
        'organization_id' => $organization->id,
        'gateway_customer_id' => 'cus_ops_123',
        'gateway_default_card_id' => 'card_old_123',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://api.pagar.me/core/v5/subscriptions/sub_ops_123/card' => Http::response([
            'id' => 'sub_ops_123',
            'status' => 'active',
            'payment_method' => 'credit_card',
            'customer_id' => 'cus_ops_123',
            'card' => [
                'id' => 'card_new_123',
            ],
        ], 200),
    ]);

    $response = $this->apiPatch('/billing/subscription/card', [
        'card_id' => 'card_new_123',
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.subscription.gateway_card_id', 'card_new_123');

    $this->assertDatabaseHas('subscriptions', [
        'id' => $subscription->id,
        'gateway_card_id' => 'card_new_123',
        'contract_status' => 'active',
    ]);
    $this->assertDatabaseHas('billing_profiles', [
        'organization_id' => $organization->id,
        'gateway_default_card_id' => 'card_new_123',
    ]);

    Http::assertSent(fn (Request $request) => $request->method() === 'PATCH'
        && $request->url() === 'https://api.pagar.me/core/v5/subscriptions/sub_ops_123/card'
        && data_get($request->data(), 'card_id') === 'card_new_123'
        && filled($request->header('Idempotency-Key')[0] ?? null));
});

it('updates the recurring subscription card with a final-submit card token and profile billing address', function () {
    configurePagarmeRecurringBillingForOpsTests();
    [, $organization] = $this->actingAsOwner();
    createRecurringOpsSubscription($organization);
    BillingProfile::factory()->create([
        'organization_id' => $organization->id,
        'gateway_customer_id' => 'cus_ops_123',
        'gateway_default_card_id' => 'card_old_123',
        'billing_address_json' => [
            'street' => 'Rua A',
            'number' => '100',
            'district' => 'Centro',
            'zip_code' => '01001000',
            'city' => 'Sao Paulo',
            'state' => 'SP',
            'country' => 'BR',
        ],
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://api.pagar.me/core/v5/subscriptions/sub_ops_123/card' => Http::response([
            'id' => 'sub_ops_123',
            'status' => 'active',
            'payment_method' => 'credit_card',
            'customer_id' => 'cus_ops_123',
            'card' => [
                'id' => 'card_tokenized_123',
            ],
        ], 200),
    ]);

    $response = $this->apiPatch('/billing/subscription/card', [
        'card_token' => 'tok_final_submit_123',
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.subscription.gateway_card_id', 'card_tokenized_123');

    Http::assertSent(fn (Request $request) => $request->method() === 'PATCH'
        && data_get($request->data(), 'card.token') === 'tok_final_submit_123'
        && data_get($request->data(), 'card.billing_address.line_1') === 'Rua A, 100, Centro'
        && data_get($request->data(), 'card.billing_address.zip_code') === '01001000');
});

it('reconciles subscription cycles invoices and charge details from the provider', function () {
    configurePagarmeRecurringBillingForOpsTests();
    [, $organization] = $this->actingAsOwner();
    $subscription = createRecurringOpsSubscription($organization);
    $periodStart = now()->startOfSecond();
    $periodEnd = $periodStart->copy()->addMonth();

    Http::preventStrayRequests();
    Http::fake([
        'https://api.pagar.me/core/v5/subscriptions/sub_ops_123' => Http::response([
            'id' => 'sub_ops_123',
            'status' => 'active',
            'payment_method' => 'credit_card',
            'customer_id' => 'cus_ops_123',
            'current_period' => [
                'start_at' => $periodStart->toISOString(),
                'end_at' => $periodEnd->toISOString(),
            ],
            'next_billing_at' => $periodEnd->toISOString(),
            'card' => [
                'id' => 'card_old_123',
            ],
        ], 200),
        'https://api.pagar.me/core/v5/subscriptions/sub_ops_123/cycles*' => Http::response([
            'data' => [[
                'id' => 'cy_ops_123',
                'status' => 'billed',
                'billing_at' => $periodEnd->toISOString(),
                'start_at' => $periodStart->toISOString(),
                'end_at' => $periodEnd->toISOString(),
            ]],
        ], 200),
        'https://api.pagar.me/core/v5/invoices*' => Http::response([
            'data' => [[
                'id' => 'inv_ops_123',
                'status' => 'paid',
                'amount' => 19900,
                'currency' => 'BRL',
                'created_at' => $periodStart->toISOString(),
                'paid_at' => $periodStart->copy()->addMinute()->toISOString(),
                'subscription' => ['id' => 'sub_ops_123'],
                'cycle' => [
                    'id' => 'cy_ops_123',
                    'status' => 'billed',
                    'billing_at' => $periodEnd->toISOString(),
                    'start_at' => $periodStart->toISOString(),
                    'end_at' => $periodEnd->toISOString(),
                ],
                'charge' => ['id' => 'ch_ops_123'],
            ]],
        ], 200),
        'https://api.pagar.me/core/v5/charges?*' => Http::response([
            'data' => [[
                'id' => 'ch_ops_123',
                'status' => 'paid',
                'amount' => 19900,
                'payment_method' => 'credit_card',
                'customer_id' => 'cus_ops_123',
                'subscription' => ['id' => 'sub_ops_123'],
                'invoice' => ['id' => 'inv_ops_123'],
            ]],
        ], 200),
        'https://api.pagar.me/core/v5/charges/ch_ops_123' => Http::response([
            'id' => 'ch_ops_123',
            'status' => 'paid',
            'amount' => 19900,
            'currency' => 'BRL',
            'payment_method' => 'credit_card',
            'paid_at' => $periodStart->copy()->addMinute()->toISOString(),
            'subscription' => ['id' => 'sub_ops_123'],
            'invoice' => ['id' => 'inv_ops_123'],
            'card' => [
                'brand' => 'visa',
                'last_four_digits' => '0010',
            ],
            'last_transaction' => [
                'id' => 'tx_ops_123',
            ],
        ], 200),
    ]);

    $response = $this->apiPost('/billing/subscription/reconcile', [
        'page' => 1,
        'size' => 20,
        'with_charge_details' => true,
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.cycles_reconciled', 1);
    $response->assertJsonPath('data.invoices_reconciled', 1);
    $response->assertJsonPath('data.charges_reconciled', 1);
    $response->assertJsonPath('data.subscription.billing_status', 'paid');

    expect(SubscriptionCycle::query()->where('gateway_cycle_id', 'cy_ops_123')->exists())->toBeTrue()
        ->and(Invoice::query()->where('gateway_invoice_id', 'inv_ops_123')->exists())->toBeTrue()
        ->and(Payment::query()->where('gateway_charge_id', 'ch_ops_123')->exists())->toBeTrue();

    $subscription->refresh();
    expect($subscription->billing_status)->toBe('paid')
        ->and($subscription->access_status)->toBe('enabled');
});
