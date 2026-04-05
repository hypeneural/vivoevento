<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Modules\Auth\Services\AccessStateBuilderService;
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
        $matrix = app(AccessStateBuilderService::class)->buildMatrix($user, $organization);

        $roles = $user->roles->map(fn ($role) => [
            'key' => $role->name,
            'name' => $this->roleDisplayName($role->name),
        ])->values();

        return $this->success([
            'roles' => $roles,
            'permissions' => $matrix['permissions'],
            'modules' => $matrix['modules'],
            'features' => $matrix['features'],
            'entitlements' => $matrix['entitlements'],
        ]);
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

}
