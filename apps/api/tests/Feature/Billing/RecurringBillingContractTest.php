<?php

use App\Modules\Billing\Models\BillingProfile;
use App\Modules\Plans\Models\Plan;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('creates a local subscription cycle before reconciling recurring invoices and payments')->todo();

it('stores a stable billing profile for the organization apart from the active subscription', function () {
    [$user, $organization] = $this->actingAsOwner();

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

    Http::preventStrayRequests();

    Http::fake([
        'https://api.pagar.me/core/v5/plans' => Http::response(['id' => 'plan_recurring_123'], 200),
        'https://api.pagar.me/core/v5/customers' => Http::response(['id' => 'cus_recurring_123'], 200),
        'https://api.pagar.me/core/v5/customers/cus_recurring_123/cards' => Http::response(['id' => 'card_recurring_123'], 200),
        'https://api.pagar.me/core/v5/subscriptions' => Http::response([
            'id' => 'sub_recurring_123',
            'status' => 'active',
        ], 200),
    ]);

    $plan = Plan::create([
        'code' => 'partner-pro',
        'name' => 'Partner Pro',
        'audience' => 'b2b',
        'status' => 'active',
    ]);

    $plan->prices()->create([
        'billing_cycle' => 'monthly',
        'currency' => 'BRL',
        'amount_cents' => 19900,
        'is_default' => true,
    ]);

    $this->apiPost('/billing/checkout', [
        'plan_id' => $plan->id,
        'billing_cycle' => 'monthly',
        'payment_method' => 'credit_card',
        'payer' => [
            'name' => 'Partner Pro LTDA',
            'email' => 'billing@partner.test',
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
        'credit_card' => [
            'card_token' => 'tok_profile_contract_123',
        ],
    ])->assertStatus(201);

    $subscription = $organization->subscription()->first();
    $billingProfile = BillingProfile::query()->where('organization_id', $organization->id)->first();

    expect($subscription)->not()->toBeNull()
        ->and($billingProfile)->not()->toBeNull()
        ->and($billingProfile?->gateway_customer_id)->toBe('cus_recurring_123')
        ->and($billingProfile?->gateway_default_card_id)->toBe('card_recurring_123')
        ->and($billingProfile?->organization_id)->toBe($organization->id)
        ->and($subscription?->gateway_customer_id)->toBe('cus_recurring_123')
        ->and($subscription?->gateway_subscription_id)->toBe('sub_recurring_123');
});

it('resolves recurring customer identity from billing_profile before attempting any email-based lookup', function () {
    [$user, $organization] = $this->actingAsOwner();

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

    BillingProfile::factory()->create([
        'organization_id' => $organization->id,
        'gateway_provider' => 'pagarme',
        'gateway_customer_id' => 'cus_existing_123',
        'gateway_default_card_id' => null,
        'payer_email' => 'legacy@partner.test',
    ]);

    Http::preventStrayRequests();

    Http::fake([
        'https://api.pagar.me/core/v5/plans' => Http::response(['id' => 'plan_recurring_123'], 200),
        'https://api.pagar.me/core/v5/customers/cus_existing_123/cards' => Http::response(['id' => 'card_existing_123'], 200),
        'https://api.pagar.me/core/v5/subscriptions' => Http::response([
            'id' => 'sub_existing_123',
            'status' => 'active',
        ], 200),
    ]);

    $plan = Plan::create([
        'code' => 'partner-pro',
        'name' => 'Partner Pro',
        'audience' => 'b2b',
        'status' => 'active',
    ]);

    $plan->prices()->create([
        'billing_cycle' => 'monthly',
        'currency' => 'BRL',
        'amount_cents' => 19900,
        'is_default' => true,
    ]);

    $this->apiPost('/billing/checkout', [
        'plan_id' => $plan->id,
        'billing_cycle' => 'monthly',
        'payment_method' => 'credit_card',
        'payer' => [
            'name' => 'Partner Pro LTDA',
            'email' => 'novo-email@partner.test',
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
        'credit_card' => [
            'card_token' => 'tok_existing_profile_123',
        ],
    ])->assertStatus(201);

    Http::assertNotSent(function (Request $request) {
        return $request->method() === 'POST'
            && $request->url() === 'https://api.pagar.me/core/v5/customers';
    });

    Http::assertSent(function (Request $request) {
        return $request->method() === 'POST'
            && $request->url() === 'https://api.pagar.me/core/v5/subscriptions'
            && data_get($request->data(), 'customer_id') === 'cus_existing_123';
    });
});

it('deduplicates recurring reconcile work by gateway subscription and invoice identifiers')->todo();
