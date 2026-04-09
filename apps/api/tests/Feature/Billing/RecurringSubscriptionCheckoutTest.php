<?php

use App\Modules\Plans\Models\Plan;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('creates a pagarme recurring subscription checkout using gateway plan customer and card ids', function () {
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
        'https://api.pagar.me/core/v5/plans' => Http::response([
            'id' => 'plan_recurring_123',
            'name' => 'Pro Parceiro Mensal',
        ], 200),
        'https://api.pagar.me/core/v5/customers' => Http::response([
            'id' => 'cus_recurring_123',
            'email' => 'financeiro@parceiro.test',
        ], 200),
        'https://api.pagar.me/core/v5/customers/cus_recurring_123/cards' => Http::response([
            'id' => 'card_recurring_123',
        ], 200),
        'https://api.pagar.me/core/v5/subscriptions' => Http::response([
            'id' => 'sub_recurring_123',
            'status' => 'active',
            'start_at' => '2026-04-09T15:00:00Z',
            'next_billing_at' => '2026-05-09T15:00:00Z',
            'card' => [
                'id' => 'card_recurring_123',
            ],
        ], 200),
    ]);

    $plan = Plan::create([
        'code' => 'pro-parceiro',
        'name' => 'Pro Parceiro',
        'audience' => 'b2b',
        'status' => 'active',
        'description' => 'Plano recorrente principal.',
    ]);

    $planPrice = $plan->prices()->create([
        'billing_cycle' => 'monthly',
        'currency' => 'BRL',
        'amount_cents' => 19900,
        'billing_type' => 'prepaid',
        'payment_methods_json' => ['credit_card'],
        'is_default' => true,
    ]);

    $response = $this->apiPost('/billing/checkout', [
        'plan_id' => $plan->id,
        'billing_cycle' => 'monthly',
        'payment_method' => 'credit_card',
        'payer' => [
            'name' => 'Parceiro Teste LTDA',
            'email' => 'financeiro@parceiro.test',
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
            'card_token' => 'tok_recurring_123',
        ],
    ]);

    $this->assertApiSuccess($response, 201);
    $response->assertJsonPath('data.plan_name', 'Pro Parceiro');
    $response->assertJsonPath('data.status', 'active');
    $response->assertJsonPath('data.payment_id', null);
    $response->assertJsonPath('data.invoice_id', null);
    $response->assertJsonPath('data.checkout.provider', 'pagarme');
    $response->assertJsonPath('data.checkout.gateway_order_id', null);

    $this->assertDatabaseHas('plan_prices', [
        'id' => $planPrice->id,
        'gateway_provider' => 'pagarme',
        'gateway_plan_id' => 'plan_recurring_123',
    ]);

    $this->assertDatabaseHas('billing_profiles', [
        'organization_id' => $organization->id,
        'gateway_provider' => 'pagarme',
        'gateway_customer_id' => 'cus_recurring_123',
        'gateway_default_card_id' => 'card_recurring_123',
        'payer_email' => 'financeiro@parceiro.test',
    ]);

    $this->assertDatabaseHas('subscriptions', [
        'organization_id' => $organization->id,
        'plan_id' => $plan->id,
        'plan_price_id' => $planPrice->id,
        'status' => 'active',
        'gateway_provider' => 'pagarme',
        'gateway_customer_id' => 'cus_recurring_123',
        'gateway_plan_id' => 'plan_recurring_123',
        'gateway_card_id' => 'card_recurring_123',
        'gateway_subscription_id' => 'sub_recurring_123',
        'payment_method' => 'credit_card',
        'contract_status' => 'active',
        'billing_status' => 'pending',
        'access_status' => 'enabled',
    ]);

    Http::assertSent(function (Request $request) {
        return $request->method() === 'POST'
            && $request->url() === 'https://api.pagar.me/core/v5/plans'
            && data_get($request->data(), 'payment_methods.0') === 'credit_card';
    });

    Http::assertSent(function (Request $request) {
        return $request->method() === 'POST'
            && $request->url() === 'https://api.pagar.me/core/v5/customers'
            && data_get($request->data(), 'email') === 'financeiro@parceiro.test';
    });

    Http::assertSent(function (Request $request) {
        return $request->method() === 'POST'
            && $request->url() === 'https://api.pagar.me/core/v5/customers/cus_recurring_123/cards'
            && data_get($request->data(), 'token') === 'tok_recurring_123';
    });

    Http::assertSent(function (Request $request) {
        return $request->method() === 'POST'
            && $request->url() === 'https://api.pagar.me/core/v5/subscriptions'
            && data_get($request->data(), 'plan_id') === 'plan_recurring_123'
            && data_get($request->data(), 'customer_id') === 'cus_recurring_123'
            && data_get($request->data(), 'card.card_id') === 'card_recurring_123'
            && data_get($request->data(), 'installments') === 1;
    });
});

it('validates recurring checkout requires payer and credit card payload when pagarme is enabled', function () {
    [$user, $organization] = $this->actingAsOwner();

    config()->set('billing.gateways.subscription', 'pagarme');

    $plan = Plan::create([
        'code' => 'starter',
        'name' => 'Starter',
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

    $this->assertApiValidationError($response, [
        'payment_method',
        'payer',
        'credit_card',
    ]);
});
