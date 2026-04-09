<?php

namespace App\Modules\Billing\Services\Pagarme;

use App\Modules\Plans\Models\Plan;
use App\Modules\Plans\Models\PlanPrice;
use InvalidArgumentException;

class PagarmePlanPayloadFactory
{
    public function build(Plan $plan, PlanPrice $planPrice, array $context = []): array
    {
        $plan->loadMissing('features');

        [$interval, $intervalCount] = $this->mapBillingCycle($planPrice->billing_cycle);
        $paymentMethods = $this->normalizePaymentMethods($planPrice->payment_methods_json ?? null);
        $billingType = $planPrice->billing_type ?: 'prepaid';
        $statementDescriptor = substr((string) config('services.pagarme.statement_descriptor', 'EVENTOVIVO'), 0, 13);

        return array_filter([
            'name' => $this->buildPlanName($plan, $planPrice),
            'description' => $plan->description,
            'payment_methods' => $paymentMethods,
            'installments' => [1],
            'statement_descriptor' => $statementDescriptor !== '' ? $statementDescriptor : null,
            'currency' => strtoupper((string) $planPrice->currency),
            'interval' => $interval,
            'interval_count' => $intervalCount,
            'trial_period_days' => $planPrice->trial_period_days,
            'billing_type' => $billingType,
            'billing_days' => $billingType === 'exact_day' && $planPrice->billing_day
                ? [(int) $planPrice->billing_day]
                : null,
            'items' => [[
                'name' => $this->buildPlanName($plan, $planPrice),
                'description' => $plan->description ?: "Plano {$plan->name}",
                'quantity' => 1,
                'pricing_scheme' => [
                    'scheme_type' => 'unit',
                    'price' => (int) $planPrice->amount_cents,
                ],
            ]],
            'metadata' => array_filter([
                'plan_id' => (string) $plan->id,
                'plan_code' => $plan->code,
                'plan_price_id' => (string) $planPrice->id,
                'billing_cycle' => $planPrice->billing_cycle,
                'audience' => $plan->audience,
                'journey' => $context['journey'] ?? 'subscription_checkout',
            ], fn (mixed $value): bool => $value !== null && $value !== ''),
        ], fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }

    private function buildPlanName(Plan $plan, PlanPrice $planPrice): string
    {
        $cycleLabel = match ($planPrice->billing_cycle) {
            'yearly' => 'Anual',
            'monthly' => 'Mensal',
            default => ucfirst((string) $planPrice->billing_cycle),
        };

        return mb_substr(trim("{$plan->name} {$cycleLabel}"), 0, 64);
    }

    private function mapBillingCycle(string $billingCycle): array
    {
        return match ($billingCycle) {
            'monthly' => ['month', 1],
            'yearly' => ['year', 1],
            default => throw new InvalidArgumentException("Unsupported recurring billing cycle [{$billingCycle}]."),
        };
    }

    /**
     * @param  array<int, string>|null  $paymentMethods
     * @return array<int, string>
     */
    private function normalizePaymentMethods(?array $paymentMethods): array
    {
        $allowed = ['credit_card', 'boleto', 'debit_card'];
        $methods = array_values(array_unique(array_filter(
            array_map(
                static fn (mixed $method): string => strtolower(trim((string) $method)),
                $paymentMethods ?? ['credit_card']
            ),
            static fn (string $method): bool => in_array($method, $allowed, true)
        )));

        return $methods !== [] ? $methods : ['credit_card'];
    }
}
