<?php

namespace App\Modules\MediaIntelligence\Enums;

enum MediaReplyTextMode: string
{
    case Disabled = 'disabled';
    case Ai = 'ai';
    case FixedRandom = 'fixed_random';

    public static function fromLegacy(?bool $enabled): self
    {
        return $enabled ? self::Ai : self::Disabled;
    }
}
