<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccessMatrixController extends BaseController
{
    /**
     * GET /api/v1/access/matrix
     *
     * Returns the full access matrix for the authenticated user.
     * The frontend can use this to rebuild the access state without a full /me call.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['roles', 'permissions']);

        $organization = $user->currentOrganization();
        $plan = $organization?->subscription?->plan;

        $permissions = $user->getAllPermissions()->pluck('name');

        // Roles
        $roles = $user->roles->map(fn ($role) => [
            'key' => $role->name,
            'name' => $this->roleDisplayName($role->name),
        ])->values();

        // Modules derived from permissions
        $moduleMap = [
            'dashboard' => true,
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
        ])->values();

        // Feature flags from plan
        $features = $this->buildFeatureFlags($plan);

        return $this->success([
            'roles' => $roles,
            'permissions' => $permissions->values(),
            'modules' => $modules,
            'features' => $features,
        ]);
    }

    private function buildFeatureFlags($plan): array
    {
        if (!$plan) {
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
}
