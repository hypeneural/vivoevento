<?php

namespace App\Modules\Wall\Enums;

enum WallTransitionMode: string
{
    case Fixed = 'fixed';
    case Random = 'random';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixa',
            self::Random => 'Aleatoria',
        };
    }
}
