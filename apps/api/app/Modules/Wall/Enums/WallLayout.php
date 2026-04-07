<?php

namespace App\Modules\Wall\Enums;

enum WallLayout: string
{
    case Auto = 'auto';
    case Polaroid = 'polaroid';
    case Fullscreen = 'fullscreen';
    case Split = 'split';
    case Cinematic = 'cinematic';
    case KenBurns = 'kenburns';
    case Spotlight = 'spotlight';
    case Gallery = 'gallery';

    public function label(): string
    {
        return match ($this) {
            self::Auto => 'Automático',
            self::Polaroid => 'Polaroid',
            self::Fullscreen => 'Tela cheia',
            self::Split => 'Dividido',
            self::Cinematic => 'Cinematográfico',
            self::KenBurns => 'Ken Burns',
            self::Spotlight => 'Holofote',
            self::Gallery => 'Galeria de arte',
        };
    }
}
