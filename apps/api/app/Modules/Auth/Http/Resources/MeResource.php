<?php

namespace App\Modules\Auth\Http\Resources;

use App\Modules\Auth\Services\AccessStateBuilderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
        $access = app(AccessStateBuilderService::class)->build($user, $organization);

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar_url' => $this->publicUrl($user->avatar_path),
                'status' => $user->status ?? 'active',
                'role' => $this->buildRole($user, $organization),
                'permissions' => $user->getAllPermissions()->pluck('name')->values(),
                'preferences' => [
                    'theme' => $user->preferences['theme'] ?? 'light',
                    'timezone' => $organization?->timezone ?? 'America/Sao_Paulo',
                    'locale' => $user->preferences['locale'] ?? 'pt-BR',
                    'email_notifications' => (bool) ($user->preferences['email_notifications'] ?? true),
                    'push_notifications' => (bool) ($user->preferences['push_notifications'] ?? false),
                    'compact_mode' => (bool) ($user->preferences['compact_mode'] ?? false),
                ],
                'last_login_at' => $user->last_login_at?->toISOString(),
            ],
            'organization' => $this->buildOrganization($organization),
            'access' => $access,
            'subscription' => $this->buildSubscription($subscription, $plan),
        ];
    }

    private function buildRole($user, $organization): ?array
    {
        $globalRole = $user->roles->pluck('name')->first(
            fn (string $role) => in_array($role, ['super-admin', 'platform-admin'], true)
        );

        if ($globalRole) {
            return [
                'key' => $globalRole,
                'name' => $this->roleDisplayName($globalRole),
            ];
        }

        $orgMembership = $organization
            ? $user->organizationMembers()->where('organization_id', $organization->id)->first()
            : null;

        $roleKey = $this->normalizeRoleKey(
            $orgMembership?->role_key ?? $user->roles->first()?->name ?? 'viewer'
        );

        return [
            'key' => $roleKey,
            'name' => $this->roleDisplayName($roleKey),
        ];
    }

    private function roleDisplayName(string $key): string
    {
        return match ($key) {
            'super-admin' => 'Super Admin',
            'platform-admin' => 'Administrador da Plataforma',
            'partner-owner' => "Propriet\u{00E1}rio",
            'partner-manager' => "Gerente da organiza\u{00E7}\u{00E3}o",
            'event-operator' => 'Operador de Evento',
            'financeiro' => 'Financeiro',
            'client' => 'Cliente',
            'viewer' => 'Visualizador',
            default => ucfirst(str_replace(['-', '_'], ' ', $key)),
        };
    }

    private function buildOrganization($organization): ?array
    {
        if (! $organization) {
            return null;
        }

        return [
            'id' => $organization->id,
            'uuid' => $organization->uuid,
            'type' => $organization->type?->value,
            'name' => $organization->displayName(),
            'slug' => $organization->slug,
            'status' => $organization->status?->value,
            'logo_url' => $this->publicUrl($organization->logo_path),
            'branding' => [
                'primary_color' => $organization->primary_color,
                'secondary_color' => $organization->secondary_color,
                'subdomain' => $organization->subdomain,
                'custom_domain' => $organization->custom_domain,
            ],
        ];
    }

    private function buildSubscription($subscription, $plan): ?array
    {
        if (! $subscription) {
            return null;
        }

        return [
            'plan_key' => $plan?->code,
            'plan_name' => $plan?->name,
            'billing_type' => $subscription->billing_cycle === 'monthly' ? 'recurring' : $subscription->billing_cycle,
            'status' => $subscription->status,
            'trial_ends_at' => $subscription->trial_ends_at?->toISOString(),
            'renews_at' => $subscription->renews_at?->toISOString(),
            'ends_at' => $subscription->ends_at?->toISOString(),
            'canceled_at' => $subscription->canceled_at?->toISOString(),
            'cancel_at_period_end' => $subscription->isCanceledPendingEnd(),
            'cancellation_effective_at' => $subscription->isCanceledPendingEnd()
                ? $subscription->ends_at?->toISOString()
                : $subscription->canceled_at?->toISOString(),
        ];
    }

    private function normalizeRoleKey(string $roleKey): string
    {
        return match ($roleKey) {
            'owner' => 'partner-owner',
            'manager' => 'partner-manager',
            default => $roleKey,
        };
    }

    private function publicUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }
}
