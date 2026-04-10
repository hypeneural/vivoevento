<?php

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\SubscriptionCycle;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Plans\Models\Plan;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function configurePagarmeRecurringBillingForConsoleTests(): void
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

function createRecurringConsoleSubscription(Organization $organization, array $attributes = []): Subscription
{
    $plan = Plan::create([
        'code' => 'console-recurring-plan',
        'name' => 'Console Recorrente',
        'audience' => 'b2b',
        'status' => 'active',
    ]);

    return Subscription::create(array_merge([
        'organization_id' => $organization->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'payment_method' => 'credit_card',
        'starts_at' => now()->subMonth(),
        'current_period_started_at' => now()->subMonth(),
        'current_period_ends_at' => now()->addDay(),
        'renews_at' => now()->addDay(),
        'next_billing_at' => now()->addDay(),
        'gateway_provider' => 'pagarme',
        'gateway_customer_id' => 'cus_console_123',
        'gateway_plan_id' => 'plan_console_123',
        'gateway_card_id' => 'card_console_123',
        'gateway_subscription_id' => 'sub_console_123',
        'contract_status' => 'active',
        'billing_status' => 'pending',
        'access_status' => 'enabled',
    ], $attributes));
}

it('runs assisted recurring reconcile from the console command', function () {
    configurePagarmeRecurringBillingForConsoleTests();
    $organization = $this->createOrganization();
    $subscription = createRecurringConsoleSubscription($organization);
    $periodStart = now()->startOfSecond();
    $periodEnd = $periodStart->copy()->addMonth();

    Http::preventStrayRequests();
    Http::fake([
        'https://api.pagar.me/core/v5/subscriptions/sub_console_123' => Http::response([
            'id' => 'sub_console_123',
            'status' => 'active',
            'payment_method' => 'credit_card',
            'customer_id' => 'cus_console_123',
            'current_period' => [
                'start_at' => $periodStart->toISOString(),
                'end_at' => $periodEnd->toISOString(),
            ],
            'next_billing_at' => $periodEnd->toISOString(),
        ], 200),
        'https://api.pagar.me/core/v5/subscriptions/sub_console_123/cycles*' => Http::response([
            'data' => [[
                'id' => 'cy_console_123',
                'status' => 'billed',
                'billing_at' => $periodEnd->toISOString(),
                'start_at' => $periodStart->toISOString(),
                'end_at' => $periodEnd->toISOString(),
            ]],
        ], 200),
        'https://api.pagar.me/core/v5/invoices*' => Http::response([
            'data' => [[
                'id' => 'inv_console_123',
                'status' => 'paid',
                'amount' => 19900,
                'currency' => 'BRL',
                'created_at' => $periodStart->toISOString(),
                'paid_at' => $periodStart->copy()->addMinute()->toISOString(),
                'subscription' => ['id' => 'sub_console_123'],
                'cycle' => [
                    'id' => 'cy_console_123',
                    'billing_at' => $periodEnd->toISOString(),
                    'start_at' => $periodStart->toISOString(),
                    'end_at' => $periodEnd->toISOString(),
                ],
                'charge' => ['id' => 'ch_console_123'],
            ]],
        ], 200),
        'https://api.pagar.me/core/v5/charges?*' => Http::response([
            'data' => [[
                'id' => 'ch_console_123',
                'status' => 'paid',
                'amount' => 19900,
                'payment_method' => 'credit_card',
                'subscription' => ['id' => 'sub_console_123'],
                'invoice' => ['id' => 'inv_console_123'],
            ]],
        ], 200),
    ]);

    $this->artisan('billing:subscriptions:reconcile', [
        '--subscription-id' => $subscription->id,
        '--with-charge-details' => '0',
    ])->assertExitCode(0);

    expect(SubscriptionCycle::query()->where('gateway_cycle_id', 'cy_console_123')->exists())->toBeTrue()
        ->and(Invoice::query()->where('gateway_invoice_id', 'inv_console_123')->exists())->toBeTrue()
        ->and(Payment::query()->where('gateway_charge_id', 'ch_console_123')->exists())->toBeTrue();
});

it('finalizes cancel_at_period_end boundary and syncs DELETE subscription with provider', function () {
    configurePagarmeRecurringBillingForConsoleTests();
    $organization = $this->createOrganization();
    $boundary = now()->subMinute()->startOfSecond();
    $subscription = createRecurringConsoleSubscription($organization, [
        'status' => 'canceled',
        'contract_status' => 'canceled',
        'billing_status' => 'paid',
        'access_status' => 'enabled',
        'cancel_at_period_end' => true,
        'cancel_requested_at' => now()->subDay(),
        'canceled_at' => now()->subDay(),
        'ends_at' => $boundary,
        'renews_at' => $boundary,
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://api.pagar.me/core/v5/subscriptions/sub_console_123' => Http::response([
            'id' => 'sub_console_123',
            'status' => 'canceled',
        ], 200),
    ]);

    $this->artisan('billing:subscriptions:finalize-period-end-cancellations', [
        '--subscription-id' => $subscription->id,
        '--reference-at' => now()->toISOString(),
    ])->assertExitCode(0);

    $subscription->refresh();
    expect($subscription->cancel_at_period_end)->toBeFalse()
        ->and($subscription->access_status)->toBe('disabled')
        ->and($subscription->renews_at)->toBeNull()
        ->and($subscription->next_billing_at)->toBeNull();

    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
        && $request->url() === 'https://api.pagar.me/core/v5/subscriptions/sub_console_123'
        && data_get($request->data(), 'cancel_pending_invoices') === true);
});
