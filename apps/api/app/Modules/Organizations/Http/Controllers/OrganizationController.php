<?php

namespace App\Modules\Organizations\Http\Controllers;

use App\Modules\Organizations\Actions\CreateOrganizationAction;
use App\Modules\Organizations\Actions\InviteCurrentOrganizationTeamMemberAction;
use App\Modules\Organizations\Actions\RemoveCurrentOrganizationTeamMemberAction;
use App\Modules\Organizations\Actions\UploadCurrentOrganizationLogoAction;
use App\Modules\Organizations\Actions\UpdateOrganizationAction;
use App\Modules\Organizations\Enums\OrganizationType;
use App\Modules\Organizations\Http\Requests\InviteCurrentOrganizationTeamMemberRequest;
use App\Modules\Organizations\Http\Requests\StoreOrganizationRequest;
use App\Modules\Organizations\Http\Requests\UploadCurrentOrganizationLogoRequest;
use App\Modules\Organizations\Http\Requests\UpdateCurrentOrganizationBrandingRequest;
use App\Modules\Organizations\Http\Requests\UpdateCurrentOrganizationRequest;
use App\Modules\Organizations\Http\Requests\UpdateOrganizationRequest;
use App\Modules\Organizations\Http\Resources\OrganizationMemberResource;
use App\Modules\Organizations\Http\Resources\OrganizationResource;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Organizations\Support\CurrentOrganizationAccess;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrganizationController extends BaseController
{
    /**
     * GET /api/v1/organizations/current
     */
    public function current(Request $request): JsonResponse
    {
        $org = $request->user()->currentOrganization();

        if (! $org) {
            return $this->error('Nenhuma organizacao encontrada', 404);
        }

        $org->loadCount(['clients', 'events']);

        return $this->success(new OrganizationResource($org));
    }

    /**
     * PATCH /api/v1/organizations/current
     */
    public function updateCurrent(UpdateCurrentOrganizationRequest $request): JsonResponse
    {
        $org = $request->user()->currentOrganization();

        if (! $org) {
            return $this->error('Nenhuma organizacao encontrada', 404);
        }

        $validated = $request->validated();

        if (array_key_exists('name', $validated) && ! array_key_exists('trade_name', $validated)) {
            $validated['trade_name'] = $validated['name'];
        }

        unset($validated['name']);

        $org->update($validated);

        return $this->success(new OrganizationResource($org->fresh()));
    }

    /**
     * PATCH /api/v1/organizations/current/branding
     */
    public function updateBranding(UpdateCurrentOrganizationBrandingRequest $request): JsonResponse
    {
        $org = $request->user()->currentOrganization();

        if (! $org) {
            return $this->error('Nenhuma organizacao encontrada', 404);
        }

        $org->update($request->validated());

        activity()
            ->performedOn($org)
            ->causedBy($request->user())
            ->log('Branding da organizacao atualizado');

        return $this->success(new OrganizationResource($org->fresh()));
    }

    /**
     * POST /api/v1/organizations/current/branding/logo
     */
    public function uploadBrandingLogo(
        UploadCurrentOrganizationLogoRequest $request,
        UploadCurrentOrganizationLogoAction $action,
    ): JsonResponse {
        $org = $request->user()->currentOrganization();

        if (! $org) {
            return $this->error('Nenhuma organizacao encontrada', 404);
        }

        return $this->success(
            $action->execute($org, $request->user(), $request->file('logo')),
        );
    }

    /**
     * GET /api/v1/organizations/current/team
     */
    public function team(Request $request): JsonResponse
    {
        abort_unless(CurrentOrganizationAccess::canManageTeam($request->user()), 403);

        $org = $request->user()->currentOrganization();

        if (! $org) {
            return $this->error('Nenhuma organizacao encontrada', 404);
        }

        $members = $org->members()
            ->with('user:id,name,email,phone,avatar_path')
            ->orderByDesc('is_owner')
            ->orderBy('id')
            ->get();

        return $this->success(OrganizationMemberResource::collection($members));
    }

    /**
     * POST /api/v1/organizations/current/team
     */
    public function inviteTeamMember(
        InviteCurrentOrganizationTeamMemberRequest $request,
        InviteCurrentOrganizationTeamMemberAction $action,
    ): JsonResponse {
        $org = $request->user()->currentOrganization();

        if (! $org) {
            return $this->error('Nenhuma organizacao encontrada', 404);
        }

        $member = $action->execute($org, $request->validated(), $request->user());

        return $this->created(new OrganizationMemberResource($member));
    }

    /**
     * DELETE /api/v1/organizations/current/team/{member}
     */
    public function removeTeamMember(
        Request $request,
        OrganizationMember $member,
        RemoveCurrentOrganizationTeamMemberAction $action,
    ): JsonResponse {
        abort_unless(CurrentOrganizationAccess::canManageTeam($request->user()), 403);

        $org = $request->user()->currentOrganization();

        if (! $org) {
            return $this->error('Nenhuma organizacao encontrada', 404);
        }

        $action->execute($org, $member, $request->user());

        return $this->noContent();
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Organization::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:180'],
            'type' => ['nullable', Rule::enum(OrganizationType::class)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $organizations = Organization::query()
            ->when($validated['search'] ?? null, function ($query, $search) use ($like) {
                $query->where(function ($builder) use ($search, $like) {
                    $builder
                        ->where('trade_name', $like, "%{$search}%")
                        ->orWhere('legal_name', $like, "%{$search}%")
                        ->orWhere('slug', $like, "%{$search}%")
                        ->orWhere('email', $like, "%{$search}%");
                });
            })
            ->when($validated['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->latest()
            ->paginate($validated['per_page'] ?? 20);

        return $this->paginated(OrganizationResource::collection($organizations));
    }

    public function store(StoreOrganizationRequest $request, CreateOrganizationAction $action): JsonResponse
    {
        $this->authorize('create', Organization::class);

        $organization = $action->execute($request->validated());

        return $this->created(new OrganizationResource($organization));
    }

    public function show(Organization $organization): JsonResponse
    {
        $this->authorize('view', $organization);

        $organization->loadCount(['clients', 'events']);

        return $this->success(new OrganizationResource($organization));
    }

    public function update(
        UpdateOrganizationRequest $request,
        Organization $organization,
        UpdateOrganizationAction $action,
    ): JsonResponse {
        $this->authorize('update', $organization);

        $organization = $action->execute($organization, $request->validated());

        return $this->success(new OrganizationResource($organization));
    }

    public function destroy(Organization $organization): JsonResponse
    {
        $this->authorize('delete', $organization);

        $organization->delete();

        return $this->noContent();
    }
}
