<?php

namespace App\Modules\Notifications\Support;

final class NotificationInboxPaginationPolicy
{
    public const DEFAULT_PER_PAGE = 20;

    public const MAX_PER_PAGE = 50;

    public const DROPDOWN_LIMIT = 8;

    public static function normalizePerPage(?int $perPage): int
    {
        if ($perPage === null || $perPage < 1) {
            return self::DEFAULT_PER_PAGE;
        }

        return min($perPage, self::MAX_PER_PAGE);
    }
}
