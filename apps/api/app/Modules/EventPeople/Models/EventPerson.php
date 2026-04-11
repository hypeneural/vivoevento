<?php

namespace App\Modules\EventPeople\Models;

use App\Modules\EventPeople\Enums\EventPersonSide;
use App\Modules\EventPeople\Enums\EventPersonStatus;
use App\Modules\EventPeople\Enums\EventPersonType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventPerson extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'display_name',
        'slug',
        'type',
        'side',
        'avatar_media_id',
        'avatar_face_id',
        'importance_rank',
        'notes',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'type' => EventPersonType::class,
        'side' => EventPersonSide::class,
        'status' => EventPersonStatus::class,
        'importance_rank' => 'integer',
    ];

    protected static function newFactory(): \Database\Factories\EventPersonFactory
    {
        return \Database\Factories\EventPersonFactory::new();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Events\Models\Event::class);
    }

    public function avatarMedia(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\MediaProcessing\Models\EventMedia::class, 'avatar_media_id');
    }

    public function avatarFace(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\FaceSearch\Models\EventMediaFace::class, 'avatar_face_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EventPersonFaceAssignment::class);
    }

    public function mediaStats(): HasMany
    {
        return $this->hasMany(EventPersonMediaStat::class);
    }

    public function representativeFaces(): HasMany
    {
        return $this->hasMany(EventPersonRepresentativeFace::class);
    }

    public function outgoingRelations(): HasMany
    {
        return $this->hasMany(EventPersonRelation::class, 'person_a_id');
    }

    public function incomingRelations(): HasMany
    {
        return $this->hasMany(EventPersonRelation::class, 'person_b_id');
    }

    public function scopeForEvent($query, int $eventId)
    {
        return $query->where('event_id', $eventId);
    }
}
