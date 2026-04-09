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
}
