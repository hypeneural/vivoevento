<?php

namespace App\Shared\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait InteractsWithActivityLogProperties
{
    protected function whereActivityPropertyIdEquals(
        Builder $query,
        string $propertyKey,
        int|string $value,
        string $boolean = 'and',
    ): Builder {
        return $query->where("properties->{$propertyKey}", '=', (string) $value, $boolean);
    }

    protected function whereActivityPropertyIdInQuery(
        Builder $query,
        string $propertyKey,
        Builder $idQuery,
        string $boolean = 'and',
    ): Builder {
        $castedIdQuery = clone $idQuery;
        $castedIdQuery->getQuery()->columns = null;
        $castedIdQuery->selectRaw('CAST(id AS TEXT)');

        return $query->whereIn("properties->{$propertyKey}", $castedIdQuery, $boolean);
    }
}
