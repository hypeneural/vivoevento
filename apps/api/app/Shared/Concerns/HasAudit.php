<?php

namespace App\Shared\Concerns;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Trait for models that should be audited via Spatie Activitylog.
 */
trait HasAudit
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(function (string $eventName) {
                $modelName = class_basename(static::class);
                return "{$modelName} was {$eventName}";
            });
    }
}
