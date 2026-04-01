<?php

namespace App\Modules\Auth\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeResource extends JsonResource
{
    /**
     * Full session payload for the authenticated user.
     * Delivers everything the frontend needs in a single call.
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource;
        $organization = $user->currentOrganization();
        $subscription = $organization?->subscription;
        $plan = $subscription?->plan;

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar_url' => $user->avatar_path,
                'status' => $user->status ?? 'active',
                'role' => $this->buildRole($user, $organization),
                'permissions' => $user->getAllPermissions()->pluck('name')->values(),
                'preferences' => [
                    'theme' => $user->preferences['theme'] ?? 'light',
                    'timezone' => $organization?->timezone ?? 'America/Sao_Paulo',
                    'locale' => $user->preferences['locale'] ?? 'pt-BR',
                ],
                'last_login_at' => $user->last_login_at?->toISOString(),
            ],
            'organization' => $this->buildOrganization($organization),
            'access' => $this->buildAccess($user, $plan),
            'subscription' => $this->buildSubscription($subscription, $plan),
        ];
    }

    private function buildRole($user, $organization): ?array
    {
        // Get the user's role from org membership or Spatie roles
        $orgMembership = $organization
            ? $user->organizationMembers()->where('organization_id', $organization->id)->first()
            : null;

        $roleKey = $orgMembership?->role_key ?? $user->roles->first()?->name ?? 'viewer';

        return [
            'key' => $roleKey,
            'name' => $this->roleDisplayName($roleKey),
        ];
    }

    private function roleDisplayName(string $key): string
    {
        return match ($key) {
            'super-admin' => 'Super Admin',
            'platform-admin' => 'Admin da Plataforma',
            'partner-owner' => 'Owner da Organização',
            'partner-manager' => 'Gerente da Organização',
            'event-operator' => 'Operador de Evento',
            'financeiro' => 'Financeiro',
            'client' => 'Cliente',
            'viewer' => 'Visualizador',
            default => ucfirst(str_replace(['-', '_'], ' ', $key)),
        };
    }

    private function buildOrganization($organization): ?array
    {
        if (!$organization) {
            return null;
        }

        return [
            'id' => $organization->id,
            'uuid' => $organization->uuid,
            'type' => $organization->type?->value,
            'name' => $organization->displayName(),
            'slug' => $organization->slug,
            'status' => $organization->status?->value,
            'logo_url' => $organization->logo_path,
            'branding' => [
                'primary_color' => $organization->primary_color,
                'secondary_color' => $organization->secondary_color,
                'subdomain' => $organization->subdomain,
                'custom_domain' => $organization->custom_domain,
            ],
        ];
    }

    private function buildAccess($user, $plan): array
    {
        $permissions = $user->getAllPermissions()->pluck('name');

        // Derive accessible modules from permissions
        $moduleMap = [
            'dashboard' => true, // always visible
            'events' => $permissions->contains('events.view'),
            'media' => $permissions->contains('media.view'),
            'moderation' => $permissions->contains('media.moderate'),
            'gallery' => $permissions->contains('gallery.view'),
            'wall' => $permissions->contains('wall.view'),
            'play' => $permissions->contains('play.view'),
            'hub' => $permissions->contains('hub.view'),
            'clients' => $permissions->contains('clients.view'),
            'plans' => $permissions->contains('plans.view') || $permissions->contains('billing.view'),
            'analytics' => $permissions->contains('analytics.view'),
            'audit' => $permissions->contains('audit.view'),
            'settings' => $permissions->contains('settings.manage'),
        ];

        $modules = collect($moduleMap)->map(fn ($enabled, $key) => [
            'key' => $key,
            'enabled' => $enabled,
            'visible' => $enabled,
        ])->values()->all();

        // Feature flags from plan
        $features = $this->buildFeatureFlags($plan);

        return [
            'accessible_modules' => collect($moduleMap)->filter()->keys()->values()->all(),
            'modules' => $modules,
            'feature_flags' => $features,
        ];
    }

    private function buildFeatureFlags($plan): array
    {
        if (!$plan) {
            // Default free tier
            return [
                'live_gallery' => true,
                'wall' => false,
                'play_memory' => false,
                'play_puzzle' => false,
                'hub' => true,
                'white_label' => false,
                'whatsapp_ingestion' => false,
                'analytics_advanced' => false,
                'custom_domain' => false,
            ];
        }

        // Derive from plan features
        $planFeatures = $plan->features?->pluck('value', 'key')->all() ?? [];

        return [
            'live_gallery' => (bool) ($planFeatures['live_gallery'] ?? true),
            'wall' => (bool) ($planFeatures['wall'] ?? false),
            'play_memory' => (bool) ($planFeatures['play_memory'] ?? false),
            'play_puzzle' => (bool) ($planFeatures['play_puzzle'] ?? false),
            'hub' => (bool) ($planFeatures['hub'] ?? true),
            'white_label' => (bool) ($planFeatures['white_label'] ?? false),
            'whatsapp_ingestion' => (bool) ($planFeatures['whatsapp_ingestion'] ?? false),
            'analytics_advanced' => (bool) ($planFeatures['analytics_advanced'] ?? false),
            'custom_domain' => (bool) ($planFeatures['custom_domain'] ?? false),
        ];
    }

    private function buildSubscription($subscription, $plan): ?array
    {
        if (!$subscription) {
            return null;
        }

        return [
            'plan_key' => $plan?->slug,
            'plan_name' => $plan?->name,
            'billing_type' => $subscription->billing_cycle === 'monthly' ? 'recurring' : $subscription->billing_cycle,
            'status' => $subscription->status,
            'trial_ends_at' => $subscription->trial_ends_at?->toISOString(),
            'renews_at' => $subscription->renews_at?->toISOString(),
        ];
    }
}
