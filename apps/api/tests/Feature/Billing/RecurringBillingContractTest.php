<?php

use App\Modules\Billing\Models\BillingProfile;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\SubscriptionCycle;
use App\Modules\Plans\Models\Plan;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('creates a local subscription cycle before reconciling recurring invoices and payments', function () {
    [$user, $organization] = $this->actingAsOwner();

    $plan = Plan::create([
        'code' => 'partner-pro',
        'name' => 'Partner Pro',
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
        'gateway_customer_id' => 'cus_recurring_123',
        'gateway_plan_id' => 'plan_recurring_123',
        'gateway_subscription_id' => 'sub_recurring_123',
        'contract_status' => 'active',
        'billing_status' => 'pending',
        'access_status' => 'enabled',
    ]);

    $invoiceCreated = app(\App\Modules\Billing\Actions\ProcessBillingWebhookAction::class)->execute('pagarme', recurringContractInvoiceCreatedWebhookPayload($subscription));

    expect($invoiceCreated['duplicate'])->toBeFalse()
        ->and(data_get($invoiceCreated, 'result.action'))->toBe('invoice_projected');

    $cycle = SubscriptionCycle::query()->where('gateway_cycle_id', 'cy_recurring_123')->first();
    $invoice = Invoice::query()->where('gateway_invoice_id', 'inv_recurring_123')->first();

    expect($cycle)->not()->toBeNull()
        ->and($invoice)->not()->toBeNull()
        ->and($invoice?->subscription_id)->toBe($subscription->id)
        ->and($invoice?->subscription_cycle_id)->toBe($cycle?->id);

    $this->assertDatabaseCount('payments', 0);

    $chargePaid = app(\App\Modules\Billing\Actions\ProcessBillingWebhookAction::class)->execute('pagarme', recurringContractChargePaidWebhookPayload($subscription));

    expect($chargePaid['duplicate'])->toBeFalse()
        ->and(data_get($chargePaid, 'result.action'))->toBe('charge_projected');

    $subscription->refresh();
    $invoice->refresh();

    $payment = Payment::query()->where('gateway_charge_id', 'ch_recurring_123')->first();

    expect($payment)->not()->toBeNull()
        ->and($payment?->invoice_id)->toBe($invoice->id)
        ->and($payment?->subscription_id)->toBe($subscription->id)
        ->and($payment?->status?->value)->toBe('paid')
        ->and($invoice->status?->value)->toBe('paid')
        ->and($subscription->billing_status)->toBe('paid');
});

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

it('deduplicates recurring reconcile work by gateway subscription and invoice identifiers', function () {
    [$user, $organization] = $this->actingAsOwner();

    $plan = Plan::create([
        'code' => 'partner-plus',
        'name' => 'Partner Plus',
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
        'gateway_customer_id' => 'cus_recurring_456',
        'gateway_plan_id' => 'plan_recurring_456',
        'gateway_subscription_id' => 'sub_recurring_456',
        'contract_status' => 'active',
        'billing_status' => 'pending',
        'access_status' => 'enabled',
    ]);

    app(\App\Modules\Billing\Actions\ProcessBillingWebhookAction::class)->execute('pagarme', recurringContractInvoiceCreatedWebhookPayload($subscription, [
        'event_key' => 'evt_recurring_invoice_created_456',
        'invoice_id' => 'inv_recurring_456',
        'cycle_id' => 'cy_recurring_456',
        'charge_id' => null,
    ]));

    app(\App\Modules\Billing\Actions\ProcessBillingWebhookAction::class)->execute('pagarme', recurringContractInvoicePaidWebhookPayload($subscription, [
        'event_key' => 'evt_recurring_invoice_paid_456',
        'invoice_id' => 'inv_recurring_456',
        'cycle_id' => 'cy_recurring_456',
        'charge_id' => 'ch_recurring_456',
    ]));

    expect(SubscriptionCycle::query()->where('gateway_cycle_id', 'cy_recurring_456')->count())->toBe(1);
    expect(Invoice::query()->where('gateway_invoice_id', 'inv_recurring_456')->count())->toBe(1);
    expect(Payment::query()->where('gateway_charge_id', 'ch_recurring_456')->count())->toBe(1);

    $invoice = Invoice::query()->where('gateway_invoice_id', 'inv_recurring_456')->firstOrFail();
    $payment = Payment::query()->where('gateway_charge_id', 'ch_recurring_456')->firstOrFail();

    expect($invoice->status?->value)->toBe('paid')
        ->and($payment->invoice_id)->toBe($invoice->id)
        ->and($payment->subscription_id)->toBe($subscription->id);
});

function recurringContractInvoiceCreatedWebhookPayload(Subscription $subscription, array $overrides = []): array
{
    $invoiceId = $overrides['invoice_id'] ?? 'inv_recurring_123';
    $cycleId = $overrides['cycle_id'] ?? 'cy_recurring_123';
    $chargeId = $overrides['charge_id'] ?? null;
    $eventKey = $overrides['event_key'] ?? 'evt_recurring_invoice_created_123';
    $issuedAt = now()->toISOString();
    $dueAt = now()->addDays(2)->toISOString();
    $periodStart = now()->startOfDay()->toISOString();
    $periodEnd = now()->addMonth()->startOfDay()->toISOString();

    return [
        'id' => $eventKey,
        'type' => 'invoice.created',
        'created_at' => $issuedAt,
        'data' => array_filter([
            'id' => $invoiceId,
            'status' => 'pending',
            'amount' => 19900,
            'currency' => 'BRL',
            'created_at' => $issuedAt,
            'due_at' => $dueAt,
            'subscription' => [
                'id' => $subscription->gateway_subscription_id,
            ],
            'cycle' => [
                'id' => $cycleId,
                'status' => 'billed',
                'billing_at' => $dueAt,
                'start_at' => $periodStart,
                'end_at' => $periodEnd,
            ],
            'charge' => $chargeId ? [
                'id' => $chargeId,
                'status' => 'pending',
                'payment_method' => 'credit_card',
            ] : null,
        ], fn (mixed $value): bool => $value !== null),
    ];
}

function recurringContractInvoicePaidWebhookPayload(Subscription $subscription, array $overrides = []): array
{
    $invoiceId = $overrides['invoice_id'] ?? 'inv_recurring_123';
    $cycleId = $overrides['cycle_id'] ?? 'cy_recurring_123';
    $chargeId = $overrides['charge_id'] ?? 'ch_recurring_123';
    $eventKey = $overrides['event_key'] ?? 'evt_recurring_invoice_paid_123';
    $paidAt = now()->toISOString();
    $periodStart = now()->startOfDay()->toISOString();
    $periodEnd = now()->addMonth()->startOfDay()->toISOString();

    return [
        'id' => $eventKey,
        'type' => 'invoice.paid',
        'created_at' => $paidAt,
        'data' => [
            'id' => $invoiceId,
            'status' => 'paid',
            'amount' => 19900,
            'currency' => 'BRL',
            'created_at' => $paidAt,
            'paid_at' => $paidAt,
            'subscription' => [
                'id' => $subscription->gateway_subscription_id,
            ],
            'cycle' => [
                'id' => $cycleId,
                'status' => 'billed',
                'billing_at' => $paidAt,
                'start_at' => $periodStart,
                'end_at' => $periodEnd,
            ],
            'charge' => [
                'id' => $chargeId,
                'status' => 'paid',
                'payment_method' => 'credit_card',
                'last_transaction' => [
                    'id' => 'tx_'.$chargeId,
                    'status' => 'paid',
                    'acquirer_return_code' => '00',
                    'acquirer_message' => 'Transacao aprovada',
                    'card' => [
                        'brand' => 'visa',
                        'last_four_digits' => '1111',
                    ],
                ],
            ],
        ],
    ];
}

function recurringContractChargePaidWebhookPayload(Subscription $subscription, array $overrides = []): array
{
    $invoiceId = $overrides['invoice_id'] ?? 'inv_recurring_123';
    $cycleId = $overrides['cycle_id'] ?? 'cy_recurring_123';
    $chargeId = $overrides['charge_id'] ?? 'ch_recurring_123';
    $eventKey = $overrides['event_key'] ?? 'evt_recurring_charge_paid_123';
    $paidAt = now()->toISOString();
    $periodStart = now()->startOfDay()->toISOString();
    $periodEnd = now()->addMonth()->startOfDay()->toISOString();

    return [
        'id' => $eventKey,
        'type' => 'charge.paid',
        'created_at' => $paidAt,
        'data' => [
            'id' => $chargeId,
            'status' => 'paid',
            'amount' => 19900,
            'currency' => 'BRL',
            'payment_method' => 'credit_card',
            'subscription' => [
                'id' => $subscription->gateway_subscription_id,
            ],
            'invoice' => [
                'id' => $invoiceId,
                'status' => 'paid',
                'amount' => 19900,
                'currency' => 'BRL',
                'created_at' => $paidAt,
                'paid_at' => $paidAt,
                'cycle' => [
                    'id' => $cycleId,
                    'status' => 'billed',
                    'billing_at' => $paidAt,
                    'start_at' => $periodStart,
                    'end_at' => $periodEnd,
                ],
            ],
            'card' => [
                'brand' => 'visa',
                'last_four_digits' => '1111',
            ],
            'last_transaction' => [
                'id' => 'tx_'.$chargeId,
                'status' => 'paid',
                'acquirer_return_code' => '00',
                'acquirer_message' => 'Transacao aprovada',
                'card' => [
                    'brand' => 'visa',
                    'last_four_digits' => '1111',
                ],
            ],
        ],
    ];
}
