<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Models\Subscription;
use App\Modules\Organizations\Models\Organization;

class OrganizationEntitlementResolverService
{
    public function resolve(?Organization $organization): array
    {
        if (! $organization) {
            return $this->defaultEntitlements();
        }

        $organization->loadMissing('subscription.plan.features');

        $subscription = $organization->subscription;
        $planFeatures = $subscription?->isActiveForEntitlements()
            ? ($subscription->plan?->features?->pluck('feature_value', 'feature_key')->all() ?? [])
            : [];
        $whiteLabel = $this->toBoolean($planFeatures['white_label.enabled'] ?? false);
        $expandedBranding = $whiteLabel || $this->toBoolean($planFeatures['branding.expanded_assets'] ?? false);
        $watermark = $expandedBranding
            || $this->toBoolean($planFeatures['branding.watermark'] ?? $planFeatures['watermark'] ?? false);

        return [
            'version' => 1,
            'organization_type' => $organization->type?->value,
            'modules' => [
                'live_gallery' => $this->toBoolean($planFeatures['live_gallery'] ?? true),
                'wall' => $this->toBoolean($planFeatures['wall.enabled'] ?? false),
                'play' => $this->toBoolean($planFeatures['play.enabled'] ?? false),
                'hub' => $this->toBoolean($planFeatures['hub.enabled'] ?? $planFeatures['hub'] ?? true),
                'whatsapp_ingestion' => $this->toBoolean($planFeatures['channels.whatsapp'] ?? false),
                'analytics_advanced' => $this->toBoolean($planFeatures['analytics_advanced'] ?? false),
            ],
            'limits' => [
                'max_active_events' => $this->toInteger($planFeatures['events.max_active'] ?? null),
                'retention_days' => $this->toInteger($planFeatures['media.retention_days'] ?? null),
            ],
            'branding' => [
                'white_label' => $whiteLabel,
                'custom_domain' => $whiteLabel || $this->toBoolean($planFeatures['custom_domain'] ?? false),
                'expanded_assets' => $expandedBranding,
                'watermark' => $watermark,
            ],
            'source_summary' => array_values(array_filter([
                $this->buildSubscriptionSummary($subscription),
            ])),
        ];
    }

    private function defaultEntitlements(): array
    {
        return [
            'version' => 1,
            'organization_type' => null,
            'modules' => [
                'live_gallery' => true,
                'wall' => false,
                'play' => false,
                'hub' => true,
                'whatsapp_ingestion' => false,
                'analytics_advanced' => false,
            ],
            'limits' => [
                'max_active_events' => null,
                'retention_days' => null,
            ],
            'branding' => [
                'white_label' => false,
                'custom_domain' => false,
                'expanded_assets' => false,
                'watermark' => false,
            ],
            'source_summary' => [],
        ];
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
