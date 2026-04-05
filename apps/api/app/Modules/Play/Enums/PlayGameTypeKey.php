<?php

namespace App\Modules\Play\Enums;

enum PlayGameTypeKey: string
{
    case Memory = 'memory';
    case Puzzle = 'puzzle';

    public function label(): string
    {
        return match ($this) {
            self::Memory => 'Jogo da Memoria',
            self::Puzzle => 'Puzzle',
        };
    }
}
