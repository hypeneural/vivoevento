<?php

namespace App\Shared\Traits;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Convenience trait combining soft-deletes.
 * Models can use this to get consistent soft-delete behavior.
 */
trait SoftDeletesModel
{
    use SoftDeletes;
}
