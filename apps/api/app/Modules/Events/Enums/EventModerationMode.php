<?php

namespace App\Modules\Events\Enums;

enum EventModerationMode: string
{
    case None = 'none';
    case Manual = 'manual';
    case Ai = 'ai';

    public static function normalize(null|string|self $value): ?string
    {
        $rawValue = $value instanceof self ? $value->value : $value;

        return match ($rawValue) {
            null => null,
            'auto' => self::None->value,
            self::None->value,
            self::Manual->value,
            self::Ai->value => $rawValue,
            default => $rawValue,
        };
    }

    public static function fromStorage(null|string|self $value): ?self
    {
        $normalized = self::normalize($value);

        return $normalized ? self::tryFrom($normalized) : null;
    }
}
