<?php

namespace App\Modules\Billing\Services\Pagarme;

use App\Modules\Billing\Models\BillingOrder;
use App\Modules\Plans\Models\Plan;
use App\Modules\Plans\Models\PlanPrice;
use Illuminate\Validation\ValidationException;

class PagarmeSubscriptionPayloadFactory
{
    public function __construct(
        private readonly PagarmeCustomerNormalizer $customerNormalizer,
    ) {}

    public function build(BillingOrder $order, Plan $plan, PlanPrice $planPrice, array $context = []): array
    {
        $paymentMethod = (string) ($context['payment_method'] ?? $order->payment_method ?? 'credit_card');
        $payer = (array) ($context['payer'] ?? $order->customer_snapshot_json ?? []);
        $gatewayPlanId = (string) ($context['gateway_plan_id'] ?? $planPrice->gateway_plan_id ?? '');
        $gatewayCustomerId = $context['gateway_customer_id'] ?? null;
        $gatewayCardId = $context['gateway_card_id'] ?? null;
        $cardToken = data_get($context, 'credit_card.card_token');

        if ($gatewayPlanId === '') {
            throw ValidationException::withMessages([
                'plan_id' => ['Nao foi possivel resolver o gateway_plan_id para a assinatura recorrente.'],
            ]);
        }

        if ($paymentMethod === 'credit_card' && ! filled($gatewayCardId) && ! filled($cardToken)) {
            throw ValidationException::withMessages([
                'credit_card.card_token' => ['Informe um card_token ou card_id valido para a assinatura recorrente.'],
            ]);
        }

        return array_filter([
            'code' => $order->uuid,
            'plan_id' => $gatewayPlanId,
            'payment_method' => $paymentMethod,
            'start_at' => $context['start_at'] ?? null,
            'customer_id' => filled($gatewayCustomerId) ? (string) $gatewayCustomerId : null,
            'customer' => filled($gatewayCustomerId) ? null : $this->customerNormalizer->normalize($payer),
            'card_id' => $paymentMethod === 'credit_card' && filled($gatewayCardId)
                ? (string) $gatewayCardId
                : null,
            'card_token' => $paymentMethod === 'credit_card' && blank($gatewayCardId) && filled($cardToken)
                ? (string) $cardToken
                : null,
            'installments' => 1,
            'metadata' => array_filter([
                'billing_order_uuid' => $order->uuid,
                'billing_order_id' => (string) $order->id,
                'organization_id' => (string) $order->organization_id,
                'plan_id' => (string) $plan->id,
                'plan_code' => $plan->code,
                'plan_price_id' => (string) $planPrice->id,
                'billing_cycle' => $planPrice->billing_cycle,
                'journey' => data_get($order->metadata_json, 'journey') ?? 'subscription_checkout',
            ], fn (mixed $value): bool => $value !== null && $value !== ''),
        ], fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }
}
