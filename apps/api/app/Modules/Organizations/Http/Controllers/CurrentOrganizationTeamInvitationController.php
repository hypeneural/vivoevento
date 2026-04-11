<?php

namespace App\Modules\Organizations\Http\Controllers;

use App\Modules\Organizations\Actions\CreateCurrentOrganizationTeamInvitationAction;
use App\Modules\Organizations\Actions\DispatchOrganizationMemberInvitationAction;
use App\Modules\Organizations\Actions\ResendOrganizationMemberInvitationAction;
use App\Modules\Organizations\Actions\RevokeOrganizationMemberInvitationAction;
use App\Modules\Organizations\Http\Requests\ResendOrganizationMemberInvitationRequest;
use App\Modules\Organizations\Http\Requests\StoreCurrentOrganizationTeamInvitationRequest;
use App\Modules\Organizations\Http\Resources\OrganizationMemberInvitationResource;
use App\Modules\Organizations\Models\OrganizationMemberInvitation;
use App\Modules\Organizations\Support\CurrentOrganizationAccess;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CurrentOrganizationTeamInvitationController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(CurrentOrganizationAccess::canManageTeam($request->user()), 403);

        $organization = $request->user()->currentOrganization();

        if (! $organization) {
            return $this->error('Nenhuma organizacao encontrada', 404);
        }

        $invitations = OrganizationMemberInvitation::query()
            ->where('organization_id', $organization->id)
            ->where('status', OrganizationMemberInvitation::STATUS_PENDING)
            ->orderByDesc('id')
            ->get();

        return $this->success(OrganizationMemberInvitationResource::collection($invitations)->resolve());
    }

    public function store(
        StoreCurrentOrganizationTeamInvitationRequest $request,
        CreateCurrentOrganizationTeamInvitationAction $action,
        DispatchOrganizationMemberInvitationAction $dispatchAction,
    ): JsonResponse {
        $organization = $request->user()->currentOrganization();

        if (! $organization) {
            return $this->error('Nenhuma organizacao encontrada', 404);
        }

        $payload = $request->validated();

        $invitation = $action->execute($organization, $payload, $request->user());
        $invitation = $dispatchAction->execute($invitation, (bool) ($payload['send_via_whatsapp'] ?? false));

        return $this->created(OrganizationMemberInvitationResource::make($invitation)->resolve());
    }

    public function resend(
        ResendOrganizationMemberInvitationRequest $request,
        OrganizationMemberInvitation $invitation,
        ResendOrganizationMemberInvitationAction $action,
    ): JsonResponse {
        abort_unless(CurrentOrganizationAccess::canManageTeam($request->user()), 403);

        $organization = $request->user()->currentOrganization();

        if (! $organization) {
            return $this->error('Nenhuma organizacao encontrada', 404);
        }

        $this->guardCurrentOrganizationInvitation($organization->id, $invitation);

        $invitation = $action->execute(
            $invitation,
            $request->user(),
            (bool) $request->validated('send_via_whatsapp'),
        );

        return $this->success(OrganizationMemberInvitationResource::make($invitation)->resolve());
    }

    public function revoke(
        Request $request,
        OrganizationMemberInvitation $invitation,
        RevokeOrganizationMemberInvitationAction $action,
    ): JsonResponse {
        abort_unless(CurrentOrganizationAccess::canManageTeam($request->user()), 403);

        $organization = $request->user()->currentOrganization();

        if (! $organization) {
            return $this->error('Nenhuma organizacao encontrada', 404);
        }

        $this->guardCurrentOrganizationInvitation($organization->id, $invitation);

        $invitation = $action->execute($invitation, $request->user());

        return $this->success(OrganizationMemberInvitationResource::make($invitation)->resolve());
    }

    private function guardCurrentOrganizationInvitation(int $organizationId, OrganizationMemberInvitation $invitation): void
    {
        if ((int) $invitation->organization_id !== $organizationId) {
            throw new NotFoundHttpException();
        }
    }
}
