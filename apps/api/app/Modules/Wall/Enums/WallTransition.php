<?php

namespace App\Modules\Wall\Enums;

enum WallTransition: string
{
    case Fade = 'fade';
    case Slide = 'slide';
    case Zoom = 'zoom';
    case Flip = 'flip';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Fade => 'Fade',
            self::Slide => 'Slide',
            self::Zoom => 'Zoom',
            self::Flip => 'Flip',
            self::None => 'Sem transição',
        };
    }
}
