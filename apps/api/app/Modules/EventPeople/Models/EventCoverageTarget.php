<?php

namespace App\Modules\EventPeople\Models;

use App\Modules\EventPeople\Enums\EventCoverageTargetType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EventCoverageTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'key',
        'label',
        'target_type',
        'person_a_id',
        'person_b_id',
        'event_person_group_id',
        'required_media_count',
        'required_published_media_count',
        'importance_rank',
        'source',
        'status',
        'metadata',
        'last_evaluated_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'target_type' => EventCoverageTargetType::class,
        'required_media_count' => 'integer',
        'required_published_media_count' => 'integer',
        'importance_rank' => 'integer',
        'metadata' => 'array',
        'last_evaluated_at' => 'datetime',
    ];

    protected static function newFactory(): \Database\Factories\EventCoverageTargetFactory
    {
        return \Database\Factories\EventCoverageTargetFactory::new();
    }

    public function personA(): BelongsTo
    {
        return $this->belongsTo(EventPerson::class, 'person_a_id');
    }

    public function personB(): BelongsTo
    {
        return $this->belongsTo(EventPerson::class, 'person_b_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(EventPersonGroup::class, 'event_person_group_id');
    }

    public function stat(): HasOne
    {
        return $this->hasOne(EventCoverageTargetStat::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(EventCoverageAlert::class);
    }

    public function activeAlert(): HasOne
    {
        return $this->hasOne(EventCoverageAlert::class)->where('status', 'active');
    }

    public function scopeForEvent($query, int $eventId)
    {
        return $query->where('event_id', $eventId);
    }
}
