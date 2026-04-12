<?php

namespace App\Modules\EventPeople\Models;

use App\Modules\EventPeople\Enums\EventCoverageState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventCoverageTargetStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'event_coverage_target_id',
        'coverage_state',
        'score',
        'resolved_entity_count',
        'media_count',
        'published_media_count',
        'joint_media_count',
        'people_with_primary_photo_count',
        'reason_codes',
        'projected_at',
    ];

    protected $casts = [
        'coverage_state' => EventCoverageState::class,
        'score' => 'float',
        'resolved_entity_count' => 'integer',
        'media_count' => 'integer',
        'published_media_count' => 'integer',
        'joint_media_count' => 'integer',
        'people_with_primary_photo_count' => 'integer',
        'reason_codes' => 'array',
        'projected_at' => 'datetime',
    ];

    protected static function newFactory(): \Database\Factories\EventCoverageTargetStatFactory
    {
        return \Database\Factories\EventCoverageTargetStatFactory::new();
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(EventCoverageTarget::class, 'event_coverage_target_id');
    }
}
