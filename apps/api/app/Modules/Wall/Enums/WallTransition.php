<?php

namespace App\Modules\Wall\Enums;

enum WallTransition: string
{
    case Fade = 'fade';
    case Slide = 'slide';
    case Zoom = 'zoom';
    case Flip = 'flip';
    case LiftFade = 'lift-fade';
    case CrossZoom = 'cross-zoom';
    case SwipeUp = 'swipe-up';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Fade => 'Fade',
            self::Slide => 'Slide',
            self::Zoom => 'Zoom',
            self::Flip => 'Flip',
            self::LiftFade => 'Elevar com fade',
            self::CrossZoom => 'Cross zoom',
            self::SwipeUp => 'Subir',
            self::None => 'Sem transicao',
        };
    }

    /**
     * @return list<self>
     */
    public static function randomPoolCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $transition): bool => $transition !== self::None,
        ));
    }

    /**
     * @return list<string>
     */
    public static function randomPoolValues(): array
    {
        return array_map(
            fn (self $transition): string => $transition->value,
            self::randomPoolCases(),
        );
    }

    /**
     * @return list<string>|null
     */
    public static function sanitizeRandomPool(mixed $pool): ?array
    {
        if (! is_array($pool)) {
            return null;
        }

        $allowed = self::randomPoolValues();
        $sanitized = [];

        foreach ($pool as $value) {
            if (! is_string($value) || ! in_array($value, $allowed, true)) {
                continue;
            }

            if (in_array($value, $sanitized, true)) {
                continue;
            }

            $sanitized[] = $value;
        }

        return $sanitized !== [] ? $sanitized : null;
    }
}
