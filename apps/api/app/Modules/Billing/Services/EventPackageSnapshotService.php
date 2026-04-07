<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Models\EventPackage;
use Illuminate\Support\Arr;

class EventPackageSnapshotService
{
    public function build(EventPackage $package): array
    {
        $package->loadMissing(['prices', 'features']);

        $flatFeatures = $package->features
            ->pluck('feature_value', 'feature_key')
            ->all();

        return $this->buildFromFlatFeatureMap($flatFeatures, $this->packageContext($package));
    }

    public function buildManualOverride(
        array $features = [],
        array $limits = [],
        ?EventPackage $package = null,
    ): array {
        $baseFlatFeatures = [];
        $packageContext = null;

        if ($package) {
            $package->loadMissing(['prices', 'features']);

            $baseFlatFeatures = $package->features
                ->pluck('feature_value', 'feature_key')
                ->all();

            $packageContext = $this->packageContext($package);
        }

        $flatFeatures = array_replace(
            $baseFlatFeatures,
            $this->normalizeFlatMap($features),
            $this->normalizeFlatMap($limits),
        );

        return $this->buildFromFlatFeatureMap($flatFeatures, $packageContext);
    }

    /**
     * @param array<string, mixed> $flatFeatures
     * @param array<string, mixed>|null $packageContext
     * @return array<string, mixed>
     */
    public function buildFromFlatFeatureMap(array $flatFeatures, ?array $packageContext = null): array
    {
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

        [$grantFeatures, $grantLimits] = $this->splitGrantSnapshots($flatFeatures);

        $eventSnapshotBase = [
            'catalog_type' => $packageContext['catalog_type'] ?? 'manual_override',
            'package_id' => $packageContext['package_id'] ?? null,
            'package_code' => $packageContext['package_code'] ?? null,
            'package_name' => $packageContext['package_name'] ?? null,
            'price_snapshot_cents' => $packageContext['price_snapshot_cents'] ?? null,
            'currency' => $packageContext['currency'] ?? null,
        ];

        return [
            'flat_features' => $flatFeatures,
            'feature_map' => $this->nestFeatureMap($flatFeatures),
            'modules' => $modules,
            'limits' => $limits,
            'branding' => $branding,
            'default_price' => $packageContext['default_price'] ?? null,
            'grant_features_snapshot' => $grantFeatures,
            'grant_limits_snapshot' => $grantLimits,
            'purchase_features_snapshot' => array_merge($grantFeatures, $grantLimits),
            'event_snapshot' => array_filter(array_merge(
                $eventSnapshotBase,
                $grantFeatures,
                $grantLimits,
            ), fn (mixed $value): bool => $value !== null),
            'order_item_snapshot' => [
                'package' => $packageContext['package'] ?? null,
                'price' => $packageContext['default_price'] ?? null,
                'modules' => $modules,
                'limits' => $limits,
                'branding' => $branding,
                'feature_map' => $this->nestFeatureMap($flatFeatures),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizeFlatMap(array $payload): array
    {
        if ($payload === []) {
            return [];
        }

        $hasNestedArrays = collect($payload)->contains(fn (mixed $value): bool => is_array($value));

        $flat = $hasNestedArrays ? Arr::dot($payload) : $payload;

        return collect($flat)
            ->filter(fn (mixed $value): bool => $value !== null)
            ->all();
    }

    /**
     * @param array<string, mixed> $flatFeatures
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function splitGrantSnapshots(array $flatFeatures): array
    {
        $grantFeatures = [];
        $grantLimits = [];

        foreach ($flatFeatures as $key => $value) {
            if ($this->isLimitKey($key)) {
                $grantLimits[$key] = $value;

                continue;
            }

            $grantFeatures[$key] = $value;
        }

        return [$grantFeatures, $grantLimits];
    }

    private function isLimitKey(string $key): bool
    {
        if (str_starts_with($key, 'limits.')) {
            return true;
        }

        return in_array($key, [
            'media.retention_days',
            'retention_days',
            'media.max_photos',
            'max_photos',
            'events.max_active',
            'max_active_events',
            'channels.whatsapp_groups.max',
            'channels.whatsapp.dedicated_instance.max_per_event',
        ], true);
    }

    /**
     * @param array<string, mixed> $flatFeatures
     * @return array<string, mixed>
     */
    private function nestFeatureMap(array $flatFeatures): array
    {
        $nested = [];

        foreach ($flatFeatures as $key => $value) {
            data_set($nested, $key, $value);
        }

        return $nested;
    }

    /**
     * @return array<string, mixed>
     */
    private function packageContext(EventPackage $package): array
    {
        $defaultPrice = $package->prices->firstWhere('is_default', true)
            ?? $package->prices->firstWhere('is_active', true)
            ?? $package->prices->first();

        return [
            'catalog_type' => 'event_package',
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'price_snapshot_cents' => $defaultPrice?->amount_cents,
            'currency' => $defaultPrice?->currency,
            'default_price' => $defaultPrice ? [
                'id' => $defaultPrice->id,
                'billing_mode' => $defaultPrice->billing_mode?->value,
                'currency' => $defaultPrice->currency,
                'amount_cents' => $defaultPrice->amount_cents,
            ] : null,
            'package' => [
                'id' => $package->id,
                'code' => $package->code,
                'name' => $package->name,
                'description' => $package->description,
                'target_audience' => $package->target_audience?->value,
            ],
        ];
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
