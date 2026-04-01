<?php

namespace App\Modules\Organizations\Http\Controllers;

use App\Modules\Organizations\Actions\CreateOrganizationAction;
use App\Modules\Organizations\Actions\UpdateOrganizationAction;
use App\Modules\Organizations\Http\Requests\StoreOrganizationRequest;
use App\Modules\Organizations\Http\Requests\UpdateOrganizationRequest;
use App\Modules\Organizations\Http\Resources\OrganizationResource;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrganizationController extends BaseController
{
    // ─── Current Organization ─────────────────────────────

    /**
     * GET /api/v1/organizations/current
     */
    public function current(Request $request): JsonResponse
    {
        $org = $request->user()->currentOrganization();

        if (!$org) {
            return $this->error('Nenhuma organização encontrada', 404);
        }

        $org->loadCount(['clients', 'events']);

        return $this->success(new OrganizationResource($org));
    }

    /**
     * PATCH /api/v1/organizations/current
     */
    public function updateCurrent(Request $request): JsonResponse
    {
        $org = $request->user()->currentOrganization();

        if (!$org) {
            return $this->error('Nenhuma organização encontrada', 404);
        }

        $validated = $request->validate([
            'trade_name' => ['sometimes', 'string', 'max:160'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'document_number' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:160'],
            'billing_email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'timezone' => ['nullable', 'string', 'max:64'],
        ]);

        $org->update($validated);

        return $this->success(new OrganizationResource($org->fresh()));
    }

    /**
     * PATCH /api/v1/organizations/current/branding
     */
    public function updateBranding(Request $request): JsonResponse
    {
        $org = $request->user()->currentOrganization();

        if (!$org) {
            return $this->error('Nenhuma organização encontrada', 404);
        }

        $validated = $request->validate([
            'logo_path' => ['nullable', 'string', 'max:255'],
            'primary_color' => ['nullable', 'string', 'max:20'],
            'secondary_color' => ['nullable', 'string', 'max:20'],
            'subdomain' => ['nullable', 'string', 'max:80', 'unique:organizations,subdomain,' . $org->id],
        ]);

        $org->update($validated);

        activity()
            ->performedOn($org)
            ->causedBy($request->user())
            ->log('Branding da organização atualizado');

        return $this->success(new OrganizationResource($org->fresh()));
    }

    // ─── Team ─────────────────────────────────────────────

    /**
     * GET /api/v1/organizations/current/team
     */
    public function team(Request $request): JsonResponse
    {
        $org = $request->user()->currentOrganization();

        if (!$org) {
            return $this->error('Nenhuma organização encontrada', 404);
        }

        $members = $org->members()
            ->with('user:id,name,email,avatar_path')
            ->get();

        return $this->success($members);
    }

    /**
     * POST /api/v1/organizations/current/team
     */
    public function inviteTeamMember(Request $request): JsonResponse
    {
        $org = $request->user()->currentOrganization();

        if (!$org) {
            return $this->error('Nenhuma organização encontrada', 404);
        }

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role_key' => ['required', 'string', 'max:60'],
        ]);

        $member = OrganizationMember::firstOrCreate(
            ['organization_id' => $org->id, 'user_id' => $validated['user_id']],
            [
                'role_key' => $validated['role_key'],
                'invited_by' => $request->user()->id,
                'status' => 'pending',
                'invited_at' => now(),
            ]
        );

        activity()
            ->performedOn($org)
            ->causedBy($request->user())
            ->withProperties(['invited_user_id' => $validated['user_id']])
            ->log('Membro convidado para a organização');

        return $this->created($member->load('user:id,name,email'));
    }

    // ─── Admin CRUD ───────────────────────────────────────

    public function index(): JsonResponse
    {
        $organizations = Organization::query()
            ->latest()
            ->paginate(20);

        return $this->paginated(OrganizationResource::collection($organizations));
    }

    public function store(StoreOrganizationRequest $request, CreateOrganizationAction $action): JsonResponse
    {
        $organization = $action->execute($request->validated());

        return $this->created(new OrganizationResource($organization));
    }

    public function show(Organization $organization): JsonResponse
    {
        $organization->loadCount(['clients', 'events']);

        return $this->success(new OrganizationResource($organization));
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization, UpdateOrganizationAction $action): JsonResponse
    {
        $organization = $action->execute($organization, $request->validated());

        return $this->success(new OrganizationResource($organization));
    }

    public function destroy(Organization $organization): JsonResponse
    {
        $organization->delete();

        return $this->noContent();
    }
}
