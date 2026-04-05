<?php

namespace App\Shared\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Provides a database-portable LIKE operator.
 *
 * PostgreSQL supports ILIKE for case-insensitive matching.
 * SQLite LIKE is already case-insensitive for ASCII by default.
 */
trait HasPortableLike
{
    protected function likeOperator(): string
    {
        return DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }
}
