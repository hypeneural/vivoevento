<?php

namespace App\Modules\EventTeam\Http\Controllers;

use App\Modules\EventTeam\Actions\AcceptEventTeamInvitationAction;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthenticatedEventTeamInvitationController extends BaseController
{
    public function accept(Request $request, string $token, AcceptEventTeamInvitationAction $action): JsonResponse
    {
        $invitation = $action->resolvePendingInvitationOrFail($token);
        $accepted = $action->execute($invitation, $request->user(), $request->validate([
            'device_name' => ['nullable', 'string', 'max:120'],
        ]));

        return $this->success($accepted);
    }
}
