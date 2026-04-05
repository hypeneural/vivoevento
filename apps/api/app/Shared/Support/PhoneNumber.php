<?php

namespace App\Shared\Support;

use Illuminate\Validation\ValidationException;

class PhoneNumber
{
    public static function digits(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    public static function looksLikeBrazilianPhone(?string $value): bool
    {
        $digits = static::digits($value);

        if ($digits === '') {
            return false;
        }

        if (str_starts_with($digits, '55')) {
            return in_array(strlen($digits), [12, 13], true);
        }

        return in_array(strlen($digits), [10, 11], true);
    }

    public static function normalizeBrazilianWhatsApp(string $value): string
    {
        $digits = static::digits($value);

        if ($digits === '') {
            throw ValidationException::withMessages([
                'phone' => ['Informe seu WhatsApp com DDD.'],
            ]);
        }

        if (str_starts_with($digits, '55') && in_array(strlen($digits), [12, 13], true)) {
            return $digits;
        }

        if (in_array(strlen($digits), [10, 11], true)) {
            return '55' . $digits;
        }

        throw ValidationException::withMessages([
            'phone' => ['Informe um WhatsApp valido com DDD.'],
        ]);
    }

    public static function normalizeBrazilianWhatsAppOrNull(?string $value): ?string
    {
        $digits = static::digits($value);

        if ($digits === '') {
            return null;
        }

        return static::normalizeBrazilianWhatsApp($digits);
    }

    public static function mask(string $value): string
    {
        $digits = static::normalizeBrazilianWhatsApp($value);
        $ddd = substr($digits, 2, 2);
        $local = substr($digits, 4);
        $visibleSuffix = substr($local, -4);
        $hiddenLength = max(strlen($local) - 4, 0);

        return sprintf('+55 (%s) %s%s', $ddd, str_repeat('*', $hiddenLength), $visibleSuffix);
    }
}
