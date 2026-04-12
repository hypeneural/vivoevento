<?php

namespace App\Modules\EventPeople\Models;

use App\Modules\EventPeople\Enums\EventCoverageAlertStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventCoverageAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'event_coverage_target_id',
        'alert_key',
        'severity',
        'title',
        'summary',
        'status',
        'payload',
        'last_evaluated_at',
    ];

    protected $casts = [
        'status' => EventCoverageAlertStatus::class,
        'payload' => 'array',
        'last_evaluated_at' => 'datetime',
    ];

    protected static function newFactory(): \Database\Factories\EventCoverageAlertFactory
    {
        return \Database\Factories\EventCoverageAlertFactory::new();
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(EventCoverageTarget::class, 'event_coverage_target_id');
    }
}
