<?php

namespace App\Modules\EventTeam\Http\Controllers;

use App\Modules\EventTeam\Actions\AcceptEventTeamInvitationAction;
use App\Modules\EventTeam\Http\Requests\AcceptPublicEventTeamInvitationRequest;
use App\Modules\EventTeam\Http\Resources\PublicEventTeamInvitationResource;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicEventTeamInvitationController extends BaseController
{
    public function show(string $token, AcceptEventTeamInvitationAction $action): JsonResponse
    {
        $invitation = $action->resolvePendingInvitationOrFail($token);

        return $this->success(PublicEventTeamInvitationResource::make($invitation)->resolve());
    }

    public function accept(
        AcceptPublicEventTeamInvitationRequest $request,
        string $token,
        AcceptEventTeamInvitationAction $action,
    ): JsonResponse {
        $invitation = $action->resolvePendingInvitationOrFail($token);
        $accepted = $action->execute($invitation, null, $request->validated());

        return $this->success($accepted);
    }
}
