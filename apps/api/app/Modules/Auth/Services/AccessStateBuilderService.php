<?php

namespace App\Modules\Auth\Services;

use App\Modules\Billing\Services\OrganizationEntitlementResolverService;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Users\Models\User;
use Illuminate\Support\Collection;

class AccessStateBuilderService
{
    public function __construct(
        private readonly OrganizationEntitlementResolverService $organizationEntitlements,
    ) {}

    public function build(User $user, ?Organization $organization): array
    {
        $permissions = $user->getAllPermissions()->pluck('name');
        $entitlements = $this->organizationEntitlements->resolve($organization);
        $featureFlags = $this->buildFeatureFlags($entitlements);

        $moduleMap = [
            'dashboard' => true,
            'events' => $permissions->contains('events.view'),
            'media' => $permissions->contains('media.view'),
            'moderation' => $permissions->contains('media.moderate'),
            'gallery' => $permissions->contains('gallery.view'),
            'wall' => $permissions->contains('wall.view') && $featureFlags['wall'],
            'play' => $permissions->contains('play.view') && ($featureFlags['play_memory'] || $featureFlags['play_puzzle']),
            'hub' => $permissions->contains('hub.view') && $featureFlags['hub'],
            'whatsapp' => $permissions->contains('channels.manage') && $featureFlags['whatsapp_ingestion'],
            'partners' => $permissions->contains('partners.manage'),
            'clients' => $permissions->contains('clients.view'),
            'plans' => $permissions->contains('plans.view') || $permissions->contains('billing.view'),
            'analytics' => $permissions->contains('analytics.view'),
            'audit' => $permissions->contains('audit.view'),
            'settings' => $permissions->contains('settings.manage'),
        ];

        return [
            'accessible_modules' => collect($moduleMap)->filter()->keys()->values()->all(),
            'modules' => collect($moduleMap)->map(fn ($enabled, $key) => [
                'key' => $key,
                'enabled' => $enabled,
                'visible' => $enabled,
            ])->values()->all(),
            'feature_flags' => $featureFlags,
            'entitlements' => $entitlements,
        ];
    }

    public function buildMatrix(User $user, ?Organization $organization): array
    {
        $access = $this->build($user, $organization);

        return [
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            'modules' => $access['modules'],
            'features' => $access['feature_flags'],
            'entitlements' => $access['entitlements'],
        ];
    }

    /**
     * @param  array<string, mixed>  $entitlements
     * @return array<string, bool>
     */
    private function buildFeatureFlags(array $entitlements): array
    {
        $modules = $entitlements['modules'] ?? [];
        $branding = $entitlements['branding'] ?? [];

        return [
            'live_gallery' => (bool) ($modules['live_gallery'] ?? true),
            'wall' => (bool) ($modules['wall'] ?? false),
            'play_memory' => (bool) ($modules['play'] ?? false),
            'play_puzzle' => (bool) ($modules['play'] ?? false),
            'hub' => (bool) ($modules['hub'] ?? true),
            'white_label' => (bool) ($branding['white_label'] ?? false),
            'whatsapp_ingestion' => (bool) ($modules['whatsapp_ingestion'] ?? false),
            'analytics_advanced' => (bool) ($modules['analytics_advanced'] ?? false),
            'custom_domain' => (bool) ($branding['custom_domain'] ?? false),
        ];
    }
}
