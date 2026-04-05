<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Enums\EntitlementMergeStrategy;
use App\Modules\Billing\Enums\EventAccessGrantSourceType;
use App\Modules\Billing\Models\EventAccessGrant;
use App\Modules\Billing\Models\EventPurchase;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Events\Enums\EventCommercialMode;
use App\Modules\Events\Models\Event;
use Illuminate\Support\Collection;

class EntitlementResolverService
{
    public function resolve(Event $event): array
    {
        $event->loadMissing([
            'modules',
            'organization.subscription.plan.features',
        ]);

        $subscription = $this->resolveSubscription($event);
        $purchase = $this->resolvePurchase($event);
        $grants = $this->resolveGrants($event);
        $eventModules = $this->eventModules($event);

        $state = $this->baseState($event, $eventModules);
        $sourceSummary = collect();

        if ($subscription) {
            $planFeatures = $subscription->plan?->features?->pluck('feature_value', 'feature_key')->all() ?? [];

            $state = $this->applySource(
                $state,
                $this->sourcePayloadFromFlatMap($planFeatures),
                EntitlementMergeStrategy::Replace,
            );

            $sourceSummary->push($this->buildSubscriptionSummary($subscription));
        }

        if ($purchase && $this->shouldApplyPurchaseSource($purchase, $grants)) {
            $state = $this->applySource(
                $state,
                $this->sourcePayloadFromFlatMap($this->purchaseFeatureMap($purchase)),
                EntitlementMergeStrategy::Replace,
            );

            $sourceSummary->push($this->buildPurchaseSummary($purchase));
        }

        foreach ($grants as $grant) {
            $state = $this->applySource(
                $state,
                [
                    'modules' => $this->extractModules((array) $grant->features_snapshot_json),
                    'limits' => $this->extractLimits((array) $grant->limits_snapshot_json),
                    'branding' => $this->extractBranding((array) $grant->features_snapshot_json),
                ],
                $grant->merge_strategy ?? EntitlementMergeStrategy::Expand,
            );

            $sourceSummary->push($this->buildGrantSummary($grant));
        }

        $commercialMode = $this->resolveCommercialMode($event, $subscription, $purchase, $grants);
        $state['commercial_mode'] = $commercialMode->value;
        $state['source_summary'] = $sourceSummary->values()->all();

        return [
            'event_id' => $event->id,
            'commercial_mode' => $commercialMode->value,
            'subscription_summary' => $this->buildSubscriptionSummary($subscription),
            'purchase_summary' => $this->buildPurchaseSummary($purchase),
            'grants_summary' => $grants->map(fn (EventAccessGrant $grant) => $this->buildGrantSummary($grant))->values()->all(),
            'event_modules' => $eventModules,
            'resolved_entitlements' => $state,
        ];
    }

    private function resolveSubscription(Event $event): ?Subscription
    {
        $subscription = $event->organization?->subscription;

        if (! $subscription || ! $subscription->isActiveForEntitlements()) {
            return null;
        }

        return $subscription;
    }

    private function resolvePurchase(Event $event): ?EventPurchase
    {
        return EventPurchase::query()
            ->with([
                'plan:id,code,name',
                'package.features:id,event_package_id,feature_key,feature_value',
                'package.prices:id,event_package_id,billing_mode,currency,amount_cents,is_active,is_default',
            ])
            ->where('event_id', $event->id)
            ->where('status', 'paid')
            ->latest('purchased_at')
            ->latest('id')
            ->first();
    }

    /**
     * @return Collection<int, EventAccessGrant>
     */
    private function resolveGrants(Event $event): Collection
    {
        return EventAccessGrant::query()
            ->with('grantedBy:id,name')
            ->where('event_id', $event->id)
            ->activeAt()
            ->orderBy('priority')
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();
    }

    private function resolveCommercialMode(
        Event $event,
        ?Subscription $subscription,
        ?EventPurchase $purchase,
        Collection $grants,
    ): EventCommercialMode {
        $primaryGrant = $grants
            ->sortByDesc(fn (EventAccessGrant $grant) => [
                $grant->priority,
                optional($grant->starts_at)->getTimestamp() ?? 0,
                $grant->id,
            ])
            ->first();

        if ($primaryGrant?->source_type instanceof EventAccessGrantSourceType) {
            return $primaryGrant->source_type->commercialMode();
        }

        if ($purchase) {
            return EventCommercialMode::SinglePurchase;
        }

        if ($subscription) {
            return EventCommercialMode::SubscriptionCovered;
        }

        return EventCommercialMode::None;
    }

    private function baseState(Event $event, array $eventModules): array
    {
        $legacySnapshot = is_array($event->purchased_plan_snapshot_json) ? $event->purchased_plan_snapshot_json : [];

        return [
            'version' => 2,
            'commercial_mode' => EventCommercialMode::None->value,
            'modules' => [
                'live' => $this->lookupBoolean($legacySnapshot, ['live.enabled', 'live_gallery', 'modules.live', 'live'], $eventModules['live']),
                'wall' => $this->lookupBoolean($legacySnapshot, ['wall.enabled', 'modules.wall', 'wall'], $eventModules['wall']),
                'play' => $this->lookupBoolean($legacySnapshot, ['play.enabled', 'modules.play', 'play'], $eventModules['play']),
                'hub' => $this->lookupBoolean($legacySnapshot, ['hub.enabled', 'hub', 'modules.hub'], $eventModules['hub']),
            ],
            'limits' => [
                'retention_days' => $this->lookupInteger($legacySnapshot, ['media.retention_days', 'limits.retention_days', 'retention_days'], $event->retention_days),
                'max_active_events' => $this->lookupInteger($legacySnapshot, ['events.max_active', 'limits.max_active_events', 'max_active_events']),
                'max_photos' => $this->lookupInteger($legacySnapshot, ['media.max_photos', 'limits.max_photos', 'max_photos']),
            ],
            'branding' => [
                'watermark' => $this->lookupBoolean($legacySnapshot, ['gallery.watermark', 'branding.watermark', 'watermark'], false),
                'white_label' => $this->lookupBoolean($legacySnapshot, ['white_label.enabled', 'branding.white_label', 'white_label'], false),
            ],
            'source_summary' => [],
        ];
    }

    private function eventModules(Event $event): array
    {
        return [
            'live' => $event->isModuleEnabled('live'),
            'wall' => $event->isModuleEnabled('wall'),
            'play' => $event->isModuleEnabled('play'),
            'hub' => $event->isModuleEnabled('hub'),
        ];
    }

    private function shouldApplyPurchaseSource(EventPurchase $purchase, Collection $grants): bool
    {
        return ! $grants->contains(function (EventAccessGrant $grant) use ($purchase) {
            if ($grant->source_type !== EventAccessGrantSourceType::EventPurchase) {
                return false;
            }

            return $grant->source_id === null || (int) $grant->source_id === $purchase->id;
        });
    }

    private function sourcePayloadFromFlatMap(array $payload): array
    {
        return [
            'modules' => $this->extractModules($payload),
            'limits' => $this->extractLimits($payload),
            'branding' => $this->extractBranding($payload),
        ];
    }

    private function extractModules(array $payload): array
    {
        return [
            'live' => $this->lookupBoolean($payload, ['live.enabled', 'live_gallery', 'modules.live', 'live']),
            'wall' => $this->lookupBoolean($payload, ['wall.enabled', 'modules.wall', 'wall']),
            'play' => $this->lookupBoolean($payload, ['play.enabled', 'modules.play', 'play']),
            'hub' => $this->lookupBoolean($payload, ['hub.enabled', 'hub', 'modules.hub']),
        ];
    }

    private function extractLimits(array $payload): array
    {
        return [
            'retention_days' => $this->lookupInteger($payload, ['media.retention_days', 'limits.retention_days', 'retention_days']),
            'max_active_events' => $this->lookupInteger($payload, ['events.max_active', 'limits.max_active_events', 'max_active_events']),
            'max_photos' => $this->lookupInteger($payload, ['media.max_photos', 'limits.max_photos', 'max_photos']),
        ];
    }

    private function extractBranding(array $payload): array
    {
        return [
            'watermark' => $this->lookupBoolean($payload, ['gallery.watermark', 'branding.watermark', 'watermark']),
            'white_label' => $this->lookupBoolean($payload, ['white_label.enabled', 'branding.white_label', 'white_label']),
        ];
    }

    private function applySource(array $state, array $payload, EntitlementMergeStrategy $mergeStrategy): array
    {
        foreach (['live', 'wall', 'play', 'hub'] as $moduleKey) {
            $state['modules'][$moduleKey] = $this->mergeBoolean(
                $state['modules'][$moduleKey],
                $payload['modules'][$moduleKey] ?? null,
                $mergeStrategy,
            );
        }

        foreach (['retention_days', 'max_active_events', 'max_photos'] as $limitKey) {
            $state['limits'][$limitKey] = $this->mergeInteger(
                $state['limits'][$limitKey] ?? null,
                $payload['limits'][$limitKey] ?? null,
                $mergeStrategy,
            );
        }

        foreach (['watermark', 'white_label'] as $brandingKey) {
            $state['branding'][$brandingKey] = $this->mergeBoolean(
                $state['branding'][$brandingKey],
                $payload['branding'][$brandingKey] ?? null,
                $mergeStrategy,
            );
        }

        return $state;
    }

    private function mergeBoolean(bool $current, ?bool $incoming, EntitlementMergeStrategy $mergeStrategy): bool
    {
        if ($incoming === null) {
            return $current;
        }

        return match ($mergeStrategy) {
            EntitlementMergeStrategy::Replace => $incoming,
            EntitlementMergeStrategy::Expand => $current || $incoming,
            EntitlementMergeStrategy::Restrict => $current && $incoming,
        };
    }

    private function mergeInteger(?int $current, ?int $incoming, EntitlementMergeStrategy $mergeStrategy): ?int
    {
        if ($incoming === null) {
            return $current;
        }

        return match ($mergeStrategy) {
            EntitlementMergeStrategy::Replace => $incoming,
            EntitlementMergeStrategy::Expand => $current === null ? $incoming : max($current, $incoming),
            EntitlementMergeStrategy::Restrict => $current === null ? $incoming : min($current, $incoming),
        };
    }

    private function buildSubscriptionSummary(?Subscription $subscription): ?array
    {
        if (! $subscription) {
            return null;
        }

        return [
            'source_type' => 'subscription',
            'status' => $subscription->status,
            'plan_id' => $subscription->plan_id,
            'plan_key' => $subscription->plan?->code,
            'plan_name' => $subscription->plan?->name,
            'billing_cycle' => $subscription->billing_cycle,
            'starts_at' => $subscription->starts_at?->toISOString(),
            'trial_ends_at' => $subscription->trial_ends_at?->toISOString(),
            'renews_at' => $subscription->renews_at?->toISOString(),
            'ends_at' => $subscription->ends_at?->toISOString(),
            'canceled_at' => $subscription->canceled_at?->toISOString(),
            'cancel_at_period_end' => $subscription->isCanceledPendingEnd(),
            'cancellation_effective_at' => $subscription->isCanceledPendingEnd()
                ? $subscription->ends_at?->toISOString()
                : $subscription->canceled_at?->toISOString(),
            'active' => $subscription->isActiveForEntitlements(),
            'priority' => EventAccessGrantSourceType::Subscription->defaultPriority(),
            'merge_strategy' => EntitlementMergeStrategy::Replace->value,
        ];
    }

    private function buildPurchaseSummary(?EventPurchase $purchase): ?array
    {
        if (! $purchase) {
            return null;
        }

        $defaultPrice = $purchase->package?->prices->firstWhere('is_default', true)
            ?? $purchase->package?->prices->first();

        return [
            'source_type' => 'event_purchase',
            'catalog_type' => $purchase->package_id ? 'event_package' : 'legacy_plan',
            'status' => $purchase->status,
            'plan_id' => $purchase->plan_id,
            'plan_key' => $purchase->plan?->code,
            'plan_name' => $purchase->plan?->name,
            'package_id' => $purchase->package_id,
            'package_code' => $purchase->package?->code,
            'package_name' => $purchase->package?->name,
            'price_snapshot_cents' => $purchase->price_snapshot_cents ?? $defaultPrice?->amount_cents,
            'currency' => $purchase->currency ?: $defaultPrice?->currency,
            'purchased_at' => $purchase->purchased_at?->toISOString(),
            'active' => true,
            'priority' => EventAccessGrantSourceType::EventPurchase->defaultPriority(),
            'merge_strategy' => EntitlementMergeStrategy::Replace->value,
        ];
    }

    private function purchaseFeatureMap(EventPurchase $purchase): array
    {
        $snapshot = (array) $purchase->features_snapshot_json;

        if ($snapshot !== []) {
            return $snapshot;
        }

        return $purchase->package?->features
            ?->pluck('feature_value', 'feature_key')
            ->all() ?? [];
    }

    private function buildGrantSummary(EventAccessGrant $grant): array
    {
        return [
            'id' => $grant->id,
            'source_type' => $grant->source_type?->value,
            'source_id' => $grant->source_id,
            'package_id' => $grant->package_id,
            'status' => $grant->status?->value,
            'priority' => $grant->priority,
            'merge_strategy' => $grant->merge_strategy?->value,
            'starts_at' => $grant->starts_at?->toISOString(),
            'ends_at' => $grant->ends_at?->toISOString(),
            'granted_by_user_id' => $grant->granted_by_user_id,
            'granted_by_name' => $grant->grantedBy?->name,
            'notes' => $grant->notes,
            'active' => true,
        ];
    }

    private function lookupBoolean(array $payload, array $keys, ?bool $fallback = null): ?bool
    {
        $value = $this->lookup($payload, $keys);

        if ($value === null) {
            return $fallback;
        }

        return $this->toBoolean($value);
    }

    private function lookupInteger(array $payload, array $keys, ?int $fallback = null): ?int
    {
        $value = $this->lookup($payload, $keys);

        if ($value === null || $value === '') {
            return $fallback;
        }

        return is_numeric($value) ? (int) $value : $fallback;
    }

    private function lookup(array $payload, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload)) {
                return $payload[$key];
            }

            $value = data_get($payload, $key);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
