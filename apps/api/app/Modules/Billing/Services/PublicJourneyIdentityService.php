<?php

namespace App\Modules\Billing\Services;

use App\Modules\Users\Models\User;
use App\Shared\Support\PhoneNumber;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PublicJourneyIdentityService
{
    /**
     * @return array{phone_exists: bool, email_exists: bool, exists: bool}
     */
    public function detectExistingIdentity(string $phone, ?string $email): array
    {
        $legacyVariant = str_starts_with($phone, '55') ? substr($phone, 2) : $phone;

        $phoneExists = User::query()
            ->where('phone', $phone)
            ->orWhere('phone', $legacyVariant)
            ->exists();

        $emailExists = $email !== null
            && User::query()->whereRaw('LOWER(email) = ?', [$email])->exists();

        return [
            'phone_exists' => $phoneExists,
            'email_exists' => $emailExists,
            'exists' => $phoneExists || $emailExists,
        ];
    }

    public function normalizePhone(string $phone): string
    {
        return PhoneNumber::normalizeBrazilianWhatsApp($phone);
    }

    public function normalizeEmail(?string $email): ?string
    {
        $email = trim((string) $email);

        return $email === '' ? null : Str::lower($email);
    }

    public function ensureIdentityAvailable(string $phone, ?string $email): void
    {
        $identity = $this->detectExistingIdentity($phone, $email);

        if ($identity['phone_exists']) {
            throw ValidationException::withMessages([
                'whatsapp' => ['Este WhatsApp ja possui cadastro. Faca login para continuar.'],
            ]);
        }

        if ($identity['email_exists']) {
            throw ValidationException::withMessages([
                'email' => ['Este e-mail ja possui cadastro. Faca login para continuar.'],
            ]);
        }
    }

    /**
     * @return array{phone: string, email: string|null}
     */
    public function alignAuthenticatedIdentity(User $user, string $phone, ?string $email): array
    {
        $userPhone = filled($user->phone) ? $this->normalizePhone((string) $user->phone) : null;
        $userEmail = $this->normalizeEmail($user->email);

        if ($userPhone !== null && $userPhone !== $phone) {
            throw ValidationException::withMessages([
                'whatsapp' => ['Entre com o mesmo WhatsApp da conta autenticada para retomar este checkout.'],
            ]);
        }

        if (
            $email !== null
            && $userEmail !== null
            && ! $this->isInternalEmail($userEmail)
            && $userEmail !== $email
        ) {
            throw ValidationException::withMessages([
                'email' => ['Entre com o mesmo e-mail da conta autenticada para retomar este checkout.'],
            ]);
        }

        return [
            'phone' => $userPhone ?? $phone,
            'email' => $this->isInternalEmail($userEmail) ? $email : ($userEmail ?? $email),
        ];
    }

    public function buildInternalEmail(string $phone): string
    {
        return "wa+{$phone}@eventovivo.local";
    }

    public function isInternalEmail(?string $email): bool
    {
        $normalized = $this->normalizeEmail($email);

        return $normalized !== null && Str::endsWith($normalized, '@eventovivo.local');
    }
}
