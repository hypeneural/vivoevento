<?php

namespace App\Modules\Organizations\Http\Controllers;

use App\Modules\Organizations\Actions\AcceptOrganizationMemberInvitationAction;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthenticatedOrganizationInvitationController extends BaseController
{
    public function accept(Request $request, string $token, AcceptOrganizationMemberInvitationAction $action): JsonResponse
    {
        $invitation = $action->resolvePendingInvitationOrFail($token);
        $accepted = $action->execute($invitation, $request->user(), $request->validate([
            'device_name' => ['nullable', 'string', 'max:120'],
        ]));

        return $this->success($accepted);
    }
}
