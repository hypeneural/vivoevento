<?php

namespace App\Modules\EventPeople\Support;

final class PersonPairKey
{
    public static function make(int $personAId, int $personBId): string
    {
        $ids = [$personAId, $personBId];
        sort($ids, SORT_NUMERIC);

        return implode(':', $ids);
    }
}
