<?php

namespace App\Modules\EventPeople\Models;

use App\Modules\EventPeople\Enums\EventRelationalCollectionStatus;
use App\Modules\EventPeople\Enums\EventRelationalCollectionType;
use App\Modules\EventPeople\Enums\EventRelationalCollectionVisibility;
use App\Modules\Events\Models\Event;
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

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
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
        return $this->hasMany(EventRelationalCollectionItem::class)->orderBy('sort_order');
    }

    public function scopeForEvent($query, int $eventId)
    {
        return $query->where('event_id', $eventId);
    }

    public function publicUrl(): ?string
    {
        if (($this->visibility?->value ?? $this->visibility) !== EventRelationalCollectionVisibility::PublicReady->value) {
            return null;
        }

        if (blank($this->share_token)) {
            return null;
        }

        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');

        return "{$frontendUrl}/momentos/{$this->share_token}";
    }

    public function publicApiUrl(): ?string
    {
        if (($this->visibility?->value ?? $this->visibility) !== EventRelationalCollectionVisibility::PublicReady->value) {
            return null;
        }

        if (blank($this->share_token)) {
            return null;
        }

        $backendUrl = rtrim((string) config('app.url'), '/');

        return "{$backendUrl}/api/v1/public/people-collections/{$this->share_token}";
    }
}
