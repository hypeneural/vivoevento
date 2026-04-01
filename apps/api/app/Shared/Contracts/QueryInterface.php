<?php

namespace App\Shared\Contracts;

use Illuminate\Database\Eloquent\Builder;

/**
 * Interface for Query objects that encapsulate complex read operations.
 */
interface QueryInterface
{
    /**
     * Build and return the query builder.
     */
    public function query(): Builder;
}
