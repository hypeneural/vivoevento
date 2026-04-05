<?php

namespace App\Modules\Billing\Services;

use App\Modules\Plans\Models\Plan;
use Illuminate\Validation\ValidationException;

class PlanSnapshotService
{
    public function build(Plan $plan, string $billingCycle = 'monthly'): array
    {
        $plan->loadMissing(['prices', 'features']);

        $flatFeatures = $plan->features
            ->pluck('feature_value', 'feature_key')
            ->all();

        $price = $plan->prices
            ->firstWhere('billing_cycle', $billingCycle)
            ?? $plan->prices->firstWhere('is_default', true)
            ?? $plan->prices->first();

        if (! $price) {
            throw ValidationException::withMessages([
                'plan_id' => ['O plano selecionado ainda nao possui preco configurado para checkout.'],
            ]);
        }

        return [
            'flat_features' => $flatFeatures,
            'feature_map' => $this->nestFeatureMap($flatFeatures),
            'price' => [
                'id' => $price->id,
                'billing_cycle' => $price->billing_cycle,
                'currency' => $price->currency,
                'amount_cents' => $price->amount_cents,
                'gateway_provider' => $price->gateway_provider,
                'gateway_price_id' => $price->gateway_price_id,
                'is_default' => (bool) $price->is_default,
            ],
            'order_item_snapshot' => [
                'plan' => [
                    'id' => $plan->id,
                    'code' => $plan->code,
                    'name' => $plan->name,
                    'audience' => $plan->audience,
                    'status' => $plan->status,
                    'description' => $plan->description,
                ],
                'price' => [
                    'id' => $price->id,
                    'billing_cycle' => $price->billing_cycle,
                    'currency' => $price->currency,
                    'amount_cents' => $price->amount_cents,
                    'gateway_provider' => $price->gateway_provider,
                    'gateway_price_id' => $price->gateway_price_id,
                    'is_default' => (bool) $price->is_default,
                ],
                'feature_map' => $this->nestFeatureMap($flatFeatures),
                'features' => $flatFeatures,
            ],
        ];
    }

    private function nestFeatureMap(array $flatFeatures): array
    {
        $nested = [];

        foreach ($flatFeatures as $key => $value) {
            data_set($nested, $key, $value);
        }

        return $nested;
    }
}
