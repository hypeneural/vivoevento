<?php

namespace App\Modules\Organizations\Http\Controllers;

use App\Modules\Organizations\Actions\AcceptOrganizationMemberInvitationAction;
use App\Modules\Organizations\Http\Requests\AcceptPublicOrganizationInvitationRequest;
use App\Modules\Organizations\Http\Resources\PublicOrganizationInvitationResource;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;

class PublicOrganizationInvitationController extends BaseController
{
    public function show(string $token, AcceptOrganizationMemberInvitationAction $action): JsonResponse
    {
        $invitation = $action->resolvePendingInvitationOrFail($token);

        return $this->success(PublicOrganizationInvitationResource::make($invitation)->resolve());
    }

    public function accept(
        AcceptPublicOrganizationInvitationRequest $request,
        string $token,
        AcceptOrganizationMemberInvitationAction $action,
    ): JsonResponse {
        $invitation = $action->resolvePendingInvitationOrFail($token);
        $accepted = $action->execute($invitation, null, $request->validated());

        return $this->success($accepted);
    }
}
