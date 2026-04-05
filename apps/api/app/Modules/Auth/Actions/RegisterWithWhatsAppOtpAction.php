<?php

namespace App\Modules\Auth\Actions;

use App\Modules\Auth\Enums\RegisterJourneyType;
use App\Modules\Auth\Services\AuthOtpDeliveryService;
use App\Modules\Organizations\Actions\CreateOrganizationAction;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Users\Models\User;
use App\Shared\Support\PhoneNumber;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RegisterWithWhatsAppOtpAction
{
    private const OTP_TTL_SECONDS = 900;
    private const RESEND_COOLDOWN_SECONDS = 30;
    private const MAX_SEND_ATTEMPTS = 5;
    private const MAX_VERIFY_ATTEMPTS = 5;
    private const MAX_REQUESTS_PER_WINDOW = 6;
    private const REQUEST_WINDOW_SECONDS = 600;

    public function __construct(
        private readonly AuthOtpDeliveryService $deliveryService,
        private readonly CreateOrganizationAction $createOrganizationAction,
    ) {}

    public function requestOtp(
        string $name,
        string $phone,
        ?string $ipAddress = null,
        RegisterJourneyType $journey = RegisterJourneyType::PartnerSignup,
    ): array
    {
        $normalizedPhone = PhoneNumber::normalizeBrazilianWhatsApp($phone);
        $displayName = trim($name);

        if ($displayName === '') {
            throw ValidationException::withMessages([
                'name' => ['Informe seu nome.'],
            ]);
        }

        $this->ensurePhoneAvailable($normalizedPhone);
        $this->hitRequestLimiter($normalizedPhone, $ipAddress, 'request');

        $state = $this->findStateByPhone($normalizedPhone);

        if ($state !== null) {
            $state['name'] = $displayName;
            $state['journey'] = $journey->value;

            $secondsUntilResend = $this->secondsUntilResend($state);

            if ($secondsUntilResend > 0) {
                $this->storeState($state);

                return $this->buildOtpResponse(
                    $state,
                    'Codigo ja enviado. Verifique seu WhatsApp.',
                    $secondsUntilResend
                );
            }
        }

        return $this->issueOtp([
            'token' => $state['token'] ?? Str::random(64),
            'name' => $displayName,
            'phone' => $normalizedPhone,
            'journey' => $journey->value,
            'send_count' => $state['send_count'] ?? 0,
            'attempts' => 0,
            'created_at' => $state['created_at'] ?? now()->timestamp,
        ]);
    }

    public function resendOtp(string $sessionToken, ?string $ipAddress = null): array
    {
        $state = $this->requireState($sessionToken);

        $this->ensurePhoneAvailable($state['phone']);
        $this->hitRequestLimiter($state['phone'], $ipAddress, 'resend');

        $secondsUntilResend = $this->secondsUntilResend($state);

        if ($secondsUntilResend > 0) {
            throw new HttpException(429, "Aguarde {$secondsUntilResend}s para reenviar o codigo.");
        }

        return $this->issueOtp($state);
    }

    public function verifyOtp(string $sessionToken, string $code, string $deviceName = 'web-panel'): array
    {
        $state = $this->requireState($sessionToken);

        $this->ensurePhoneAvailable($state['phone']);

        $attempts = (int) ($state['attempts'] ?? 0);

        if ($attempts >= self::MAX_VERIFY_ATTEMPTS) {
            $this->forgetState($state);

            throw new HttpException(429, 'Muitas tentativas de validacao. Solicite um novo codigo.');
        }

        $state['attempts'] = $attempts + 1;
        $this->storeState($state);

        if (! Hash::check($code, $state['code_hash'] ?? '')) {
            $remainingAttempts = max(self::MAX_VERIFY_ATTEMPTS - $state['attempts'], 0);

            throw ValidationException::withMessages([
                'code' => ["Codigo invalido. Restam {$remainingAttempts} tentativa(s)."],
            ]);
        }

        $journey = RegisterJourneyType::tryFrom((string) ($state['journey'] ?? ''))
            ?? RegisterJourneyType::fallback();

        $user = DB::transaction(function () use ($state, $journey) {
            $user = User::create([
                'name' => $state['name'],
                'email' => $this->buildInternalEmail($state['phone']),
                'phone' => $state['phone'],
                'password' => Str::password(32),
                'status' => 'active',
                'last_login_at' => now(),
            ]);

            $organization = $this->createOrganizationAction->execute([
                'name' => $state['name'],
                'type' => $journey->organizationType()->value,
                'status' => 'active',
                'timezone' => 'America/Sao_Paulo',
            ]);

            OrganizationMember::create([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'role_key' => 'partner-owner',
                'is_owner' => true,
                'status' => 'active',
                'joined_at' => now(),
            ]);

            $user->assignRole('partner-owner');

            activity()
                ->event('auth.register')
                ->performedOn($user)
                ->causedBy($user)
                ->withProperties([
                    'organization_id' => $organization->id,
                    'phone' => $state['phone'],
                    'journey' => $journey->value,
                    'organization_type' => $organization->type?->value,
                ])
                ->log('Cadastro concluido com OTP via WhatsApp');

            return $user;
        });

        $token = $user->createToken($deviceName)->plainTextToken;

        $this->forgetState($state);

        return [
            'message' => 'WhatsApp validado com sucesso.',
            'user' => $user,
            'token' => $token,
            'onboarding' => [
                'title' => "Bem-vindo, {$state['name']}!",
                'description' => $journey->onboardingDescription(),
                'next_path' => $journey->nextPath(),
            ],
        ];
    }

    private function issueOtp(array $state): array
    {
        $sendCount = (int) ($state['send_count'] ?? 0);

        if ($sendCount >= self::MAX_SEND_ATTEMPTS) {
            throw new HttpException(429, 'Voce atingiu o limite de envios. Tente novamente em alguns minutos.');
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addSeconds(self::OTP_TTL_SECONDS);
        $resendAt = now()->addSeconds(self::RESEND_COOLDOWN_SECONDS);

        $state['attempts'] = 0;
        $state['send_count'] = $sendCount + 1;
        $state['code_hash'] = Hash::make($code);
        $state['expires_at'] = $expiresAt->timestamp;
        $state['resend_available_at'] = $resendAt->timestamp;
        $state['updated_at'] = now()->timestamp;

        $this->storeState($state);
        $this->sendOtpMessage($state['phone'], $code);

        return $this->buildOtpResponse(
            $state,
            'Enviamos um codigo de 6 digitos para o seu WhatsApp.',
            self::RESEND_COOLDOWN_SECONDS,
            $code
        );
    }

    private function sendOtpMessage(string $phone, string $code): void
    {
        $this->deliveryService->sendSignupWhatsAppOtp($phone, $code);
    }

    private function ensurePhoneAvailable(string $phone): void
    {
        $legacyVariant = str_starts_with($phone, '55') ? substr($phone, 2) : $phone;

        $exists = User::query()
            ->where('phone', $phone)
            ->orWhere('phone', $legacyVariant)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'phone' => ['Este WhatsApp ja possui cadastro.'],
            ]);
        }
    }

    private function hitRequestLimiter(string $phone, ?string $ipAddress, string $scope): void
    {
        $ip = $ipAddress ?: 'unknown';
        $key = sprintf('signup_otp:%s:%s:%s', $scope, $phone, $ip);

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

    private function findStateByPhone(string $phone): ?array
    {
        $sessionToken = Cache::get($this->phoneIndexCacheKey($phone));

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
        Cache::put($this->phoneIndexCacheKey($state['phone']), $state['token'], $expiresAt);
    }

    private function forgetState(array $state): void
    {
        Cache::forget($this->sessionCacheKey($state['token']));
        Cache::forget($this->phoneIndexCacheKey($state['phone']));
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
            'delivery' => 'whatsapp',
            'phone_masked' => PhoneNumber::mask($state['phone']),
            'expires_in' => max(((int) ($state['expires_at'] ?? 0)) - now()->timestamp, 0),
            'resend_in' => $resendIn,
        ];

        if (app()->environment('testing') && $debugCode !== null) {
            $response['debug_code'] = $debugCode;
        }

        return $response;
    }

    private function buildInternalEmail(string $phone): string
    {
        return "wa+{$phone}@eventovivo.local";
    }

    private function sessionCacheKey(string $sessionToken): string
    {
        return "signup_otp:session:{$sessionToken}";
    }

    private function phoneIndexCacheKey(string $phone): string
    {
        return "signup_otp:phone:{$phone}";
    }
}
