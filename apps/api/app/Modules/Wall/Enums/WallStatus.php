<?php

namespace App\Modules\Wall\Enums;

enum WallStatus: string
{
    case Draft = 'draft';
    case Live = 'live';
    case Paused = 'paused';
    case Stopped = 'stopped';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::Live => 'Ao Vivo',
            self::Paused => 'Pausado',
            self::Stopped => 'Parado',
            self::Expired => 'Expirado',
        };
    }

    public function isPlayable(): bool
    {
        return $this === self::Live;
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Live, self::Paused], true);
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Draft => in_array($target, [self::Live], true),
            self::Live => in_array($target, [self::Paused, self::Stopped, self::Expired], true),
            self::Paused => in_array($target, [self::Live, self::Stopped, self::Expired], true),
            self::Stopped => in_array($target, [self::Live, self::Draft], true),
            self::Expired => false,
        };
    }
}
