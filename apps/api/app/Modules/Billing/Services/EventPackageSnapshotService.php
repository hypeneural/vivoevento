<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Models\EventPackage;

class EventPackageSnapshotService
{
    public function build(EventPackage $package): array
    {
        $package->loadMissing(['prices', 'features']);

        $flatFeatures = $package->features
            ->pluck('feature_value', 'feature_key')
            ->all();

        $modules = [
            'live' => $this->toBoolean($flatFeatures['live.enabled'] ?? $flatFeatures['live_gallery'] ?? $flatFeatures['modules.live'] ?? $flatFeatures['live'] ?? true),
            'wall' => $this->toBoolean($flatFeatures['wall.enabled'] ?? $flatFeatures['modules.wall'] ?? $flatFeatures['wall'] ?? false),
            'play' => $this->toBoolean($flatFeatures['play.enabled'] ?? $flatFeatures['modules.play'] ?? $flatFeatures['play'] ?? false),
            'hub' => $this->toBoolean($flatFeatures['hub.enabled'] ?? $flatFeatures['modules.hub'] ?? $flatFeatures['hub'] ?? true),
        ];

        $limits = [
            'retention_days' => $this->toInteger($flatFeatures['media.retention_days'] ?? $flatFeatures['limits.retention_days'] ?? $flatFeatures['retention_days'] ?? null),
            'max_photos' => $this->toInteger($flatFeatures['media.max_photos'] ?? $flatFeatures['limits.max_photos'] ?? $flatFeatures['max_photos'] ?? null),
        ];

        $branding = [
            'watermark' => $this->toBoolean($flatFeatures['gallery.watermark'] ?? $flatFeatures['branding.watermark'] ?? $flatFeatures['watermark'] ?? false),
            'white_label' => $this->toBoolean($flatFeatures['white_label.enabled'] ?? $flatFeatures['branding.white_label'] ?? $flatFeatures['white_label'] ?? false),
        ];

        $defaultPrice = $package->prices->firstWhere('is_default', true)
            ?? $package->prices->firstWhere('is_active', true)
            ?? $package->prices->first();

        $grantFeatures = array_filter([
            'live.enabled' => $modules['live'],
            'wall.enabled' => $modules['wall'],
            'play.enabled' => $modules['play'],
            'hub.enabled' => $modules['hub'],
            'gallery.watermark' => $branding['watermark'],
            'white_label.enabled' => $branding['white_label'],
        ], fn (mixed $value): bool => $value !== null);

        $grantLimits = array_filter([
            'media.retention_days' => $limits['retention_days'],
            'media.max_photos' => $limits['max_photos'],
        ], fn (mixed $value): bool => $value !== null);

        return [
            'flat_features' => $flatFeatures,
            'feature_map' => $this->nestFeatureMap($flatFeatures),
            'modules' => $modules,
            'limits' => $limits,
            'branding' => $branding,
            'default_price' => $defaultPrice ? [
                'id' => $defaultPrice->id,
                'billing_mode' => $defaultPrice->billing_mode?->value,
                'currency' => $defaultPrice->currency,
                'amount_cents' => $defaultPrice->amount_cents,
            ] : null,
            'grant_features_snapshot' => $grantFeatures,
            'grant_limits_snapshot' => $grantLimits,
            'purchase_features_snapshot' => array_merge($grantFeatures, $grantLimits),
            'event_snapshot' => array_filter(array_merge([
                'catalog_type' => 'event_package',
                'package_id' => $package->id,
                'package_code' => $package->code,
                'package_name' => $package->name,
                'price_snapshot_cents' => $defaultPrice?->amount_cents,
                'currency' => $defaultPrice?->currency,
            ], $grantFeatures, $grantLimits), fn (mixed $value): bool => $value !== null),
            'order_item_snapshot' => [
                'package' => [
                    'id' => $package->id,
                    'code' => $package->code,
                    'name' => $package->name,
                    'description' => $package->description,
                    'target_audience' => $package->target_audience?->value,
                ],
                'price' => $defaultPrice ? [
                    'id' => $defaultPrice->id,
                    'billing_mode' => $defaultPrice->billing_mode?->value,
                    'currency' => $defaultPrice->currency,
                    'amount_cents' => $defaultPrice->amount_cents,
                ] : null,
                'modules' => $modules,
                'limits' => $limits,
                'branding' => $branding,
                'feature_map' => $this->nestFeatureMap($flatFeatures),
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
