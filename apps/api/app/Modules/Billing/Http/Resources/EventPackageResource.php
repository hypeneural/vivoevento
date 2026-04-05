<?php

namespace App\Modules\Billing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventPackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $flatFeatureMap = $this->features
            ->mapWithKeys(fn ($feature) => [$feature->feature_key => $feature->feature_value])
            ->all();
        $featureMap = $this->nestFeatureMap($flatFeatureMap);

        $defaultPrice = $this->prices->firstWhere('is_default', true) ?? $this->prices->first();

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'target_audience' => $this->target_audience?->value,
            'is_active' => (bool) $this->is_active,
            'sort_order' => $this->sort_order,
            'default_price' => $defaultPrice ? [
                'id' => $defaultPrice->id,
                'billing_mode' => $defaultPrice->billing_mode?->value,
                'currency' => $defaultPrice->currency,
                'amount_cents' => $defaultPrice->amount_cents,
                'is_active' => (bool) $defaultPrice->is_active,
                'is_default' => (bool) $defaultPrice->is_default,
            ] : null,
            'prices' => $this->prices->map(fn ($price) => [
                'id' => $price->id,
                'billing_mode' => $price->billing_mode?->value,
                'currency' => $price->currency,
                'amount_cents' => $price->amount_cents,
                'is_active' => (bool) $price->is_active,
                'is_default' => (bool) $price->is_default,
            ])->values()->all(),
            'features' => $this->features->map(fn ($feature) => [
                'id' => $feature->id,
                'feature_key' => $feature->feature_key,
                'feature_value' => $feature->feature_value,
            ])->values()->all(),
            'feature_map' => $featureMap,
            'modules' => [
                'hub' => $this->toBoolean($flatFeatureMap['hub.enabled'] ?? $flatFeatureMap['hub'] ?? true),
                'wall' => $this->toBoolean($flatFeatureMap['wall.enabled'] ?? false),
                'play' => $this->toBoolean($flatFeatureMap['play.enabled'] ?? false),
            ],
            'limits' => [
                'retention_days' => $this->toInteger($flatFeatureMap['media.retention_days'] ?? null),
                'max_photos' => $this->toInteger($flatFeatureMap['media.max_photos'] ?? null),
            ],
        ];
    }

    private function nestFeatureMap(array $flatFeatureMap): array
    {
        $nested = [];

        foreach ($flatFeatureMap as $key => $value) {
            data_set($nested, $key, $value);
        }

        return $nested;
    }

    private function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function toInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }
}
