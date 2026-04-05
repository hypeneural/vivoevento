<?php

namespace App\Modules\Auth\Actions;

use App\Modules\Auth\Services\AuthOtpDeliveryService;
use App\Modules\Users\Models\User;
use App\Shared\Support\PhoneNumber;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ResetPasswordWithOtpAction
{
    private const OTP_TTL_SECONDS = 900;
    private const RESEND_COOLDOWN_SECONDS = 30;
    private const MAX_SEND_ATTEMPTS = 5;
    private const MAX_VERIFY_ATTEMPTS = 5;
    private const MAX_REQUESTS_PER_WINDOW = 6;
    private const REQUEST_WINDOW_SECONDS = 600;

    public function __construct(
        private readonly AuthOtpDeliveryService $deliveryService,
    ) {}

    public function requestOtp(string $login, bool $isPhone, ?string $ipAddress = null): array
    {
        $identifier = $isPhone
            ? PhoneNumber::normalizeBrazilianWhatsApp($login)
            : strtolower(trim($login));

        $this->hitRequestLimiter($identifier, $ipAddress, 'request');

        $state = $this->findStateByLogin($identifier);

        if ($state !== null) {
            $secondsUntilResend = $this->secondsUntilResend($state);

            if ($secondsUntilResend > 0) {
                $this->storeState($state);

                return $this->buildOtpResponse(
                    $state,
                    'Se encontrarmos sua conta, vamos enviar um codigo de 6 digitos para confirmar sua identidade.',
                    $secondsUntilResend
                );
            }
        }

        $user = $this->findUserByIdentifier($identifier, $isPhone);

        return $this->issueOtp([
            'token' => $state['token'] ?? Str::random(64),
            'login' => $identifier,
            'delivery' => $isPhone ? 'whatsapp' : 'email',
            'destination' => $identifier,
            'destination_masked' => $isPhone ? PhoneNumber::mask($identifier) : $this->maskEmail($identifier),
            'user_id' => $user?->id,
            'send_count' => $state['send_count'] ?? 0,
            'attempts' => 0,
            'verified' => false,
            'created_at' => $state['created_at'] ?? now()->timestamp,
        ], $user);
    }

    public function resendOtp(string $sessionToken, ?string $ipAddress = null): array
    {
        $state = $this->requireState($sessionToken);

        $this->hitRequestLimiter($state['login'], $ipAddress, 'resend');

        $secondsUntilResend = $this->secondsUntilResend($state);

        if ($secondsUntilResend > 0) {
            throw new HttpException(429, "Aguarde {$secondsUntilResend}s para reenviar o codigo.");
        }

        return $this->issueOtp($state, $this->resolveUserFromState($state));
    }

    public function verifyOtp(string $sessionToken, string $code): array
    {
        $state = $this->requireState($sessionToken);

        if (($state['verified'] ?? false) === true) {
            return $this->buildVerifyResponse($state);
        }

        $attempts = (int) ($state['attempts'] ?? 0);

        if ($attempts >= self::MAX_VERIFY_ATTEMPTS) {
            $this->forgetState($state);

            throw new HttpException(429, 'Muitas tentativas de validacao. Solicite um novo codigo.');
        }

        $state['attempts'] = $attempts + 1;
        $this->storeState($state);

        $user = $this->resolveUserFromState($state);

        if ($user === null || ! Hash::check($code, $state['code_hash'] ?? '')) {
            $remainingAttempts = max(self::MAX_VERIFY_ATTEMPTS - $state['attempts'], 0);

            throw ValidationException::withMessages([
                'code' => ["Codigo invalido. Restam {$remainingAttempts} tentativa(s)."],
            ]);
        }

        $state['verified'] = true;
        $state['verified_at'] = now()->timestamp;
        $state['attempts'] = 0;
        $this->storeState($state);

        activity()
            ->performedOn($user)
            ->withProperties([
                'delivery' => $state['delivery'],
            ])
            ->log('OTP de recuperacao de senha validado');

        return $this->buildVerifyResponse($state);
    }

    public function resetPassword(string $sessionToken, string $password, string $deviceName = 'web-panel'): array
    {
        $state = $this->requireState($sessionToken);

        if (! ($state['verified'] ?? false)) {
            throw ValidationException::withMessages([
                'session_token' => ['Valide o codigo antes de redefinir a senha.'],
            ]);
        }

        $user = $this->resolveUserFromState($state);

        if ($user === null) {
            $this->forgetState($state);

            throw ValidationException::withMessages([
                'session_token' => ['Sessao expirada. Solicite um novo codigo.'],
            ]);
        }

        $user->update([
            'password' => $password,
            'last_login_at' => now(),
        ]);

        $user->tokens()->delete();

        $token = $user->createToken($deviceName)->plainTextToken;

        $this->forgetState($state);

        activity()
            ->performedOn($user)
            ->log('Senha redefinida com OTP');

        return [
            'message' => 'Senha redefinida com sucesso.',
            'user' => $user->fresh(),
            'token' => $token,
        ];
    }

    private function issueOtp(array $state, ?User $user): array
    {
        $sendCount = (int) ($state['send_count'] ?? 0);

        if ($sendCount >= self::MAX_SEND_ATTEMPTS) {
            throw new HttpException(429, 'Voce atingiu o limite de envios. Tente novamente em alguns minutos.');
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addSeconds(self::OTP_TTL_SECONDS);
        $resendAt = now()->addSeconds(self::RESEND_COOLDOWN_SECONDS);

        $state['attempts'] = 0;
        $state['verified'] = false;
        $state['verified_at'] = null;
        $state['send_count'] = $sendCount + 1;
        $state['code_hash'] = Hash::make($code);
        $state['expires_at'] = $expiresAt->timestamp;
        $state['resend_available_at'] = $resendAt->timestamp;
        $state['updated_at'] = now()->timestamp;

        $this->storeState($state);

        if ($user !== null) {
            $this->dispatchOtp($state, $user, $code);
        }

        return $this->buildOtpResponse(
            $state,
            'Se encontrarmos sua conta, vamos enviar um codigo de 6 digitos para confirmar sua identidade.',
            self::RESEND_COOLDOWN_SECONDS,
            $code
        );
    }

    private function dispatchOtp(array $state, User $user, string $code): void
    {
        if ($state['delivery'] === 'whatsapp') {
            $this->deliveryService->sendPasswordResetWhatsAppOtp($state['destination'], $code);
        } else {
            $this->deliveryService->sendPasswordResetEmailOtp($user->email, $code);
        }

        activity()
            ->performedOn($user)
            ->withProperties([
                'delivery' => $state['delivery'],
            ])
            ->log('Solicitacao de recuperacao de senha');
    }

    private function findUserByIdentifier(string $identifier, bool $isPhone): ?User
    {
        if (! $isPhone) {
            return User::query()->where('email', $identifier)->first();
        }

        $legacyVariant = str_starts_with($identifier, '55') ? substr($identifier, 2) : $identifier;

        return User::query()
            ->where('phone', $identifier)
            ->orWhere('phone', $legacyVariant)
            ->first();
    }

    private function resolveUserFromState(array $state): ?User
    {
        $userId = $state['user_id'] ?? null;

        if (! is_numeric($userId)) {
            return null;
        }

        return User::query()->find($userId);
    }

    private function hitRequestLimiter(string $login, ?string $ipAddress, string $scope): void
    {
        $ip = $ipAddress ?: 'unknown';
        $key = sprintf('password_reset_otp:%s:%s:%s', $scope, $login, $ip);

        if (RateLimiter::tooManyAttempts($key, self::MAX_REQUESTS_PER_WINDOW)) {
            throw new HttpException(429, 'Voce excedeu o limite de solicitacoes. Tente novamente em instantes.');
        }

        RateLimiter::hit($key, self::REQUEST_WINDOW_SECONDS);
    }

    private function requireState(string $sessionToken): array
    {
        $state = Cache::get($this->sessionCacheKey($sessionToken));

        if (! is_array($state)) {
            throw ValidationException::withMessages([
                'session_token' => ['Sessao expirada. Solicite um novo codigo.'],
            ]);
        }

        return $state;
    }

    private function findStateByLogin(string $login): ?array
    {
        $sessionToken = Cache::get($this->loginIndexCacheKey($login));

        if (! is_string($sessionToken)) {
            return null;
        }

        $state = Cache::get($this->sessionCacheKey($sessionToken));

        return is_array($state) ? $state : null;
    }

    private function storeState(array $state): void
    {
        $expiresAt = now()->addSeconds(self::OTP_TTL_SECONDS);

        Cache::put($this->sessionCacheKey($state['token']), $state, $expiresAt);
        Cache::put($this->loginIndexCacheKey($state['login']), $state['token'], $expiresAt);
    }

    private function forgetState(array $state): void
    {
        Cache::forget($this->sessionCacheKey($state['token']));
        Cache::forget($this->loginIndexCacheKey($state['login']));
    }

    private function secondsUntilResend(array $state): int
    {
        return max(((int) ($state['resend_available_at'] ?? 0)) - now()->timestamp, 0);
    }

    private function buildOtpResponse(
        array $state,
        string $message,
        int $resendIn,
        ?string $debugCode = null,
    ): array {
        $response = [
            'message' => $message,
            'session_token' => $state['token'],
            'method' => $state['delivery'],
            'destination_masked' => $state['destination_masked'],
            'expires_in' => max(((int) ($state['expires_at'] ?? 0)) - now()->timestamp, 0),
            'resend_in' => $resendIn,
        ];

        if (app()->environment('testing') && $debugCode !== null) {
            $response['debug_code'] = $debugCode;
        }

        return $response;
    }

    private function buildVerifyResponse(array $state): array
    {
        return [
            'message' => 'Codigo validado com sucesso.',
            'session_token' => $state['token'],
            'method' => $state['delivery'],
            'destination_masked' => $state['destination_masked'],
        ];
    }

    private function maskEmail(string $email): string
    {
        [$localPart, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ($domain === '') {
            return $email;
        }

        $visibleStart = substr($localPart, 0, 1) ?: '*';
        $visibleEnd = strlen($localPart) > 2 ? substr($localPart, -1) : '';
        $hidden = str_repeat('*', max(strlen($localPart) - strlen($visibleStart . $visibleEnd), 2));

        return "{$visibleStart}{$hidden}{$visibleEnd}@{$domain}";
    }

    private function sessionCacheKey(string $sessionToken): string
    {
        return "password_reset_otp:session:{$sessionToken}";
    }

    private function loginIndexCacheKey(string $login): string
    {
        return "password_reset_otp:login:{$login}";
    }
}
