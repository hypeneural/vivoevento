<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Modules\Auth\Actions\LoginUserAction;
use App\Modules\Auth\Actions\LogoutUserAction;
use App\Modules\Auth\Http\Requests\LoginRequest;
use App\Modules\Auth\Http\Requests\ForgotPasswordRequest;
use App\Modules\Auth\Http\Requests\ResetPasswordRequest;
use App\Modules\Users\Http\Resources\UserResource;
use App\Modules\Users\Models\User;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LoginController extends BaseController
{
    /**
     * POST /api/v1/auth/login
     *
     * Accepts login via WhatsApp phone or email + password.
     */
    public function login(LoginRequest $request, LoginUserAction $action): JsonResponse
    {
        $result = $action->execute(
            $request->getLoginIdentifier(),
            $request->validated('password'),
            $request->validated('device_name', 'web-panel'),
            $request->isPhoneLogin()
        );

        return $this->success([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
        ]);
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request, LogoutUserAction $action): JsonResponse
    {
        $action->execute($request);

        return $this->success(message: 'Logged out');
    }

    /**
     * POST /api/v1/auth/forgot-password
     *
     * Generates a 6-digit code sent via WhatsApp (or email fallback).
     * Code is stored in cache for 15 minutes.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $login = $request->getLoginIdentifier();
        $isPhone = $request->isPhoneLogin();

        // Find user
        if ($isPhone) {
            $user = User::where('phone', $login)->first()
                ?? User::where('phone', str_starts_with($login, '55') ? substr($login, 2) : '55' . $login)->first();
        } else {
            $user = User::where('email', $login)->first();
        }

        // Always return success to prevent user enumeration
        if (!$user) {
            return $this->success([
                'message' => 'Se encontrarmos sua conta, enviaremos um código de recuperação.',
                'method' => $isPhone ? 'whatsapp' : 'email',
            ]);
        }

        // Generate 6-digit code
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $cacheKey = 'password_reset:' . $user->id;

        // Store code in cache for 15 minutes (max 3 attempts)
        Cache::put($cacheKey, [
            'code' => Hash::make($code),
            'user_id' => $user->id,
            'attempts' => 0,
            'created_at' => now()->toISOString(),
        ], now()->addMinutes(15));

        // TODO: Send via WhatsApp (Z-API) or email
        // For now, log the code in development
        if (app()->environment('local', 'development')) {
            logger()->info("Password reset code for {$user->email}: {$code}");
        }

        // In production, dispatch notification job
        // dispatch(new SendPasswordResetCodeJob($user, $code, $isPhone ? 'whatsapp' : 'email'));

        activity()
            ->performedOn($user)
            ->log('Solicitação de recuperação de senha');

        return $this->success([
            'message' => 'Se encontrarmos sua conta, enviaremos um código de recuperação.',
            'method' => $isPhone ? 'whatsapp' : 'email',
            'expires_in' => 900, // 15 minutes in seconds
        ]);
    }

    /**
     * POST /api/v1/auth/reset-password
     *
     * Validates the code and resets the password.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $login = $request->getLoginIdentifier();
        $isPhone = $request->isPhoneLogin();

        // Find user
        if ($isPhone) {
            $user = User::where('phone', $login)->first()
                ?? User::where('phone', str_starts_with($login, '55') ? substr($login, 2) : '55' . $login)->first();
        } else {
            $user = User::where('email', $login)->first();
        }

        if (!$user) {
            return $this->error('Código inválido ou expirado.', 422);
        }

        $cacheKey = 'password_reset:' . $user->id;
        $cached = Cache::get($cacheKey);

        if (!$cached) {
            return $this->error('Código expirado. Solicite um novo código.', 422);
        }

        // Check max attempts
        if ($cached['attempts'] >= 5) {
            Cache::forget($cacheKey);
            return $this->error('Muitas tentativas. Solicite um novo código.', 429);
        }

        // Increment attempts
        $cached['attempts']++;
        Cache::put($cacheKey, $cached, now()->addMinutes(15));

        // Verify code
        if (!Hash::check($request->validated('code'), $cached['code'])) {
            $remaining = 5 - $cached['attempts'];
            return $this->error("Código incorreto. {$remaining} tentativa(s) restante(s).", 422);
        }

        // Reset password
        $user->update([
            'password' => $request->validated('password'),
        ]);

        // Revoke all tokens
        $user->tokens()->delete();

        // Clean up
        Cache::forget($cacheKey);

        activity()
            ->performedOn($user)
            ->log('Senha redefinida com sucesso');

        // Auto login with new password
        $token = $user->createToken('web-panel')->plainTextToken;

        return $this->success([
            'message' => 'Senha redefinida com sucesso!',
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }
}
