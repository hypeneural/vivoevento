<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Models\BillingOrder;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Plans\Models\Plan;
use App\Modules\Plans\Models\PlanPrice;

class ManualSubscriptionGatewayService implements BillingSubscriptionGatewayInterface
{
    public function providerKey(): string
    {
        return 'manual';
    }

    public function ensurePlan(Plan $plan, PlanPrice $planPrice, array $context = []): array
    {
        return [
            'provider_key' => $this->providerKey(),
            'gateway_plan_id' => $planPrice->gateway_plan_id ?: "manual-plan-price-{$planPrice->id}",
            'created' => false,
            'idempotency_key' => null,
        ];
    }

    public function createSubscription(BillingOrder $order, Plan $plan, PlanPrice $planPrice, array $context = []): array
    {
        $startsAt = now();
        $renewsAt = $planPrice->billing_cycle === 'yearly'
            ? $startsAt->copy()->addYear()
            : $startsAt->copy()->addMonth();

        return [
            'provider_key' => $this->providerKey(),
            'idempotency_key' => null,
            'gateway_plan_id' => $context['gateway_plan_id'] ?? $planPrice->gateway_plan_id ?: "manual-plan-price-{$planPrice->id}",
            'gateway_subscription_id' => "manual-subscription-{$order->uuid}",
            'gateway_customer_id' => null,
            'gateway_card_id' => null,
            'gateway_status' => 'active',
            'gateway_status_reason' => null,
            'status' => 'active',
            'contract_status' => 'active',
            'billing_status' => 'paid',
            'access_status' => 'enabled',
            'payment_method' => $context['payment_method'] ?? 'manual',
            'billing_type' => $planPrice->billing_type ?? 'prepaid',
            'starts_at' => $startsAt,
            'next_billing_at' => $renewsAt,
            'current_period_started_at' => $startsAt,
            'current_period_ends_at' => $renewsAt,
            'gateway_response' => [
                'id' => "manual-subscription-{$order->uuid}",
                'status' => 'active',
            ],
        ];
    }

    public function cancelSubscription(Subscription $subscription, array $context = []): array
    {
        return [
            'provider_key' => $this->providerKey(),
            'gateway_subscription_id' => $subscription->gateway_subscription_id,
            'gateway_status' => 'canceled',
            'gateway_response' => [
                'id' => $subscription->gateway_subscription_id,
                'status' => 'canceled',
            ],
        ];
    }

    public function fetchSubscription(Subscription $subscription, array $context = []): array
    {
        return [
            'id' => $subscription->gateway_subscription_id ?: "manual-subscription-{$subscription->id}",
            'status' => $subscription->contract_status ?: $subscription->status,
            'payment_method' => $subscription->payment_method,
            'start_at' => $subscription->starts_at?->toISOString(),
            'next_billing_at' => $subscription->next_billing_at?->toISOString(),
            'current_period_started_at' => $subscription->current_period_started_at?->toISOString(),
            'current_period_end_at' => $subscription->current_period_ends_at?->toISOString(),
            'customer_id' => $subscription->gateway_customer_id,
            'card' => [
                'id' => $subscription->gateway_card_id,
            ],
        ];
    }

    public function listCycles(Subscription $subscription, array $query = []): array
    {
        return [
            'data' => $subscription->cycles()
                ->orderByDesc('period_end_at')
                ->get()
                ->map(fn ($cycle) => [
                    'id' => $cycle->gateway_cycle_id ?: (string) $cycle->id,
                    'status' => $cycle->status,
                    'billing_at' => $cycle->billing_at?->toISOString(),
                    'start_at' => $cycle->period_start_at?->toISOString(),
                    'end_at' => $cycle->period_end_at?->toISOString(),
                    'closed_at' => $cycle->closed_at?->toISOString(),
                ])
                ->all(),
        ];
    }

    public function listInvoices(Subscription $subscription, array $query = []): array
    {
        return [
            'data' => $subscription->invoices()
                ->orderByDesc('issued_at')
                ->get()
                ->map(fn ($invoice) => [
                    'id' => $invoice->gateway_invoice_id ?: (string) $invoice->id,
                    'status' => $invoice->gateway_status ?: $invoice->status?->value,
                    'amount' => $invoice->amount_cents,
                    'currency' => $invoice->currency,
                    'created_at' => $invoice->issued_at?->toISOString(),
                    'paid_at' => $invoice->paid_at?->toISOString(),
                    'due_at' => $invoice->due_at?->toISOString(),
                    'subscription' => [
                        'id' => $subscription->gateway_subscription_id,
                    ],
                    'charge_id' => $invoice->gateway_charge_id,
                    'cycle' => $invoice->subscriptionCycle ? [
                        'id' => $invoice->subscriptionCycle->gateway_cycle_id,
                        'status' => $invoice->subscriptionCycle->status,
                        'billing_at' => $invoice->subscriptionCycle->billing_at?->toISOString(),
                        'start_at' => $invoice->subscriptionCycle->period_start_at?->toISOString(),
                        'end_at' => $invoice->subscriptionCycle->period_end_at?->toISOString(),
                    ] : null,
                ])
                ->all(),
        ];
    }

    public function listCharges(Subscription $subscription, array $query = []): array
    {
        return [
            'data' => $subscription->payments()
                ->orderByDesc('id')
                ->get()
                ->map(fn ($payment) => [
                    'id' => $payment->gateway_charge_id ?: $payment->gateway_payment_id ?: (string) $payment->id,
                    'status' => $payment->gateway_charge_status ?: $payment->status?->value,
                    'amount' => $payment->amount_cents,
                    'currency' => $payment->currency,
                    'payment_method' => $payment->payment_method,
                    'customer_id' => $subscription->gateway_customer_id,
                    'subscription' => [
                        'id' => $subscription->gateway_subscription_id,
                    ],
                    'invoice' => $payment->invoice ? [
                        'id' => $payment->invoice->gateway_invoice_id ?: (string) $payment->invoice->id,
                    ] : null,
                    'card' => [
                        'id' => $payment->gateway_payment_id,
                        'brand' => $payment->card_brand,
                        'last_four_digits' => $payment->card_last_four,
                    ],
                ])
                ->all(),
        ];
    }

    public function getCharge(Subscription $subscription, string $chargeId, array $context = []): array
    {
        return collect($this->listCharges($subscription, $context)['data'] ?? [])
            ->firstWhere('id', $chargeId) ?? [
            'id' => $chargeId,
            'status' => 'pending',
            'subscription' => [
                'id' => $subscription->gateway_subscription_id,
            ],
        ];
    }

    public function listCustomerCards(Subscription $subscription, array $context = []): array
    {
        if (! filled($subscription->gateway_card_id)) {
            return ['data' => []];
        }

        return [
            'data' => [[
                'id' => $subscription->gateway_card_id,
                'brand' => 'manual',
                'holder_name' => 'Cartao manual',
                'last_four_digits' => '0000',
                'status' => 'active',
            ]],
        ];
    }

    public function updateSubscriptionCard(Subscription $subscription, array $context = []): array
    {
        $gatewayCardId = $context['card_id']
            ?? $subscription->gateway_card_id
            ?? "manual-card-{$subscription->id}";

        $subscription->forceFill([
            'gateway_card_id' => $gatewayCardId,
        ])->save();

        return [
            'provider_key' => $this->providerKey(),
            'gateway_subscription_id' => $subscription->gateway_subscription_id,
            'gateway_customer_id' => $subscription->gateway_customer_id,
            'gateway_card_id' => $gatewayCardId,
            'gateway_response' => [
                'id' => $subscription->gateway_subscription_id,
                'status' => $subscription->contract_status ?: $subscription->status,
                'card' => [
                    'id' => $gatewayCardId,
                ],
            ],
        ];
    }
}
