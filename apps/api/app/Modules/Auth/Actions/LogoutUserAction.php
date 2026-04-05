<?php

namespace App\Modules\Auth\Actions;

use Illuminate\Http\Request;
use Laravel\Sanctum\TransientToken;

class LogoutUserAction
{
    public function execute(Request $request): void
    {
        $user = $request->user();
        $token = $user->currentAccessToken();
        $tokenId = $token instanceof TransientToken ? null : $token?->id;
        $tokenName = $token instanceof TransientToken ? null : $token?->name;

        activity()
            ->event('auth.logout')
            ->performedOn($user)
            ->causedBy($user)
            ->withProperties([
                'organization_id' => $user->currentOrganization()?->id,
                'token_id' => $tokenId,
                'token_name' => $tokenName,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ])
            ->log('Logout realizado');

        // TransientToken (used in testing) doesn't support delete
        if ($token && !($token instanceof TransientToken)) {
            $token->delete();
        }
    }
}
