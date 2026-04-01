<?php

namespace App\Modules\Auth\Actions;

use Illuminate\Http\Request;
use Laravel\Sanctum\TransientToken;

class LogoutUserAction
{
    public function execute(Request $request): void
    {
        $token = $request->user()->currentAccessToken();

        // TransientToken (used in testing) doesn't support delete
        if ($token && !($token instanceof TransientToken)) {
            $token->delete();
        }
    }
}
