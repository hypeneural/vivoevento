<?php

namespace App\Modules\Wall\Enums;

enum WallAcceptedOrientation: string
{
    case All = 'all';
    case Landscape = 'landscape';
    case Portrait = 'portrait';

    public function label(): string
    {
        return match ($this) {
            self::All => 'Todas as orientações',
            self::Landscape => 'Apenas paisagem (horizontal)',
            self::Portrait => 'Apenas retrato (vertical)',
        };
    }

    /**
     * Check if a media orientation matches this filter.
     * 'squareish' media is accepted by both landscape and portrait.
     */
    public function matches(?string $mediaOrientation): bool
    {
        if ($this === self::All) {
            return true;
        }

        if ($mediaOrientation === null) {
            return true; // Unknown orientation — allow through
        }

        if ($mediaOrientation === 'squareish') {
            return true; // Square media accepted everywhere
        }

        return match ($this) {
            self::Landscape => $mediaOrientation === 'horizontal',
            self::Portrait => $mediaOrientation === 'vertical',
            default => true,
        };
    }
}
