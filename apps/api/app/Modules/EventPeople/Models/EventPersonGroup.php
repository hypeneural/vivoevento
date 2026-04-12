<?php

namespace App\Modules\EventPeople\Models;

use App\Modules\EventPeople\Enums\EventPersonSide;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EventPersonGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'display_name',
        'slug',
        'group_type',
        'side',
        'notes',
        'importance_rank',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'side' => EventPersonSide::class,
        'importance_rank' => 'integer',
    ];

    protected static function newFactory(): \Database\Factories\EventPersonGroupFactory
    {
        return \Database\Factories\EventPersonGroupFactory::new();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Events\Models\Event::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(EventPersonGroupMembership::class);
    }

    public function groupStat(): HasOne
    {
        return $this->hasOne(EventPersonGroupStat::class);
    }

    public function groupMediaStat(): HasOne
    {
        return $this->hasOne(EventPersonGroupMediaStat::class);
    }

    public function scopeForEvent($query, int $eventId)
    {
        return $query->where('event_id', $eventId);
    }
}
