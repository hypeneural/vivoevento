<?php

namespace App\Modules\EventPeople\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventPersonGroupMembership extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'event_person_group_id',
        'event_person_id',
        'role_label',
        'source',
        'confidence',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'confidence' => 'float',
    ];

    protected static function newFactory(): \Database\Factories\EventPersonGroupMembershipFactory
    {
        return \Database\Factories\EventPersonGroupMembershipFactory::new();
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(EventPersonGroup::class, 'event_person_group_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(EventPerson::class, 'event_person_id');
    }

    public function scopeForEvent($query, int $eventId)
    {
        return $query->where('event_id', $eventId);
    }
}
