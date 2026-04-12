<?php

namespace App\Modules\EventPeople\Models;

use App\Modules\EventPeople\Enums\EventRelationalCollectionStatus;
use App\Modules\EventPeople\Enums\EventRelationalCollectionType;
use App\Modules\EventPeople\Enums\EventRelationalCollectionVisibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventRelationalCollection extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'collection_key',
        'collection_type',
        'source_type',
        'person_a_id',
        'person_b_id',
        'event_person_group_id',
        'display_name',
        'status',
        'visibility',
        'share_token',
        'metadata',
        'generated_at',
        'published_at',
    ];

    protected $casts = [
        'collection_type' => EventRelationalCollectionType::class,
        'status' => EventRelationalCollectionStatus::class,
        'visibility' => EventRelationalCollectionVisibility::class,
        'metadata' => 'array',
        'generated_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    protected static function newFactory(): \Database\Factories\EventRelationalCollectionFactory
    {
        return \Database\Factories\EventRelationalCollectionFactory::new();
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

    public function items(): HasMany
    {
        return $this->hasMany(EventRelationalCollectionItem::class);
    }

    public function scopeForEvent($query, int $eventId)
    {
        return $query->where('event_id', $eventId);
    }
}
