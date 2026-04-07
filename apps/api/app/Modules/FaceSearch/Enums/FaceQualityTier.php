<?php

namespace App\Modules\FaceSearch\Enums;

enum FaceQualityTier: string
{
    case Reject = 'reject';
    case IndexOnly = 'index_only';
    case SearchPriority = 'search_priority';

    public function rank(): int
    {
        return match ($this) {
            self::Reject => 0,
            self::IndexOnly => 1,
            self::SearchPriority => 2,
        };
    }

    public static function rankFor(?string $value): int
    {
        return self::tryFrom((string) $value)?->rank() ?? self::Reject->rank();
    }
}
