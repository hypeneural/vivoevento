<?php

namespace App\Modules\Auth\Actions;

use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginUserAction
{
    /**
     * Authenticate a user by phone or email + password.
     *
     * @param string $login Phone number (digits) or email
     * @param bool $isPhone Whether the login identifier is a phone number
     */
    public function execute(
        string $login,
        string $password,
        string $deviceName = 'api',
        bool $isPhone = false,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): array
    {
        if ($isPhone) {
            // Search by phone — try with and without country code
            $user = User::where('phone', $login)->first();

            // If not found with full number, try without country code
            if (!$user && str_starts_with($login, '55')) {
                $user = User::where('phone', substr($login, 2))->first();
            }

            // Also try with country code if provided without it
            if (!$user && !str_starts_with($login, '55')) {
                $user = User::where('phone', '55' . $login)->first();
            }
        } else {
            $user = User::where('email', $login)->first();
        }

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => [$isPhone
                    ? 'WhatsApp ou senha incorretos.'
                    : 'E-mail ou senha incorretos.'],
            ]);
        }

        if ($user->status === 'blocked' || $user->status === 'inactive') {
            throw ValidationException::withMessages([
                'login' => ['Sua conta está desativada. Entre em contato com o suporte.'],
            ]);
        }

        $user->update(['last_login_at' => now()]);

        $token = $user->createToken($deviceName)->plainTextToken;

        activity()
            ->event('auth.login')
            ->performedOn($user)
            ->causedBy($user)
            ->withProperties([
                'organization_id' => $user->currentOrganization()?->id,
                'device_name' => $deviceName,
                'login_method' => $isPhone ? 'phone' : 'email',
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ])
            ->log('Login realizado');

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}
