<?php

namespace App\Modules\EventPeople\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventPersonCooccurrence extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'person_a_id',
        'person_b_id',
        'person_pair_key',
        'co_photo_count',
        'solo_photo_count_a',
        'solo_photo_count_b',
        'average_face_distance',
        'weighted_score',
        'last_seen_together_at',
    ];

    protected $casts = [
        'co_photo_count' => 'integer',
        'solo_photo_count_a' => 'integer',
        'solo_photo_count_b' => 'integer',
        'average_face_distance' => 'float',
        'weighted_score' => 'float',
        'last_seen_together_at' => 'datetime',
    ];

    protected static function newFactory(): \Database\Factories\EventPersonCooccurrenceFactory
    {
        return \Database\Factories\EventPersonCooccurrenceFactory::new();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Events\Models\Event::class);
    }

    public function personA(): BelongsTo
    {
        return $this->belongsTo(EventPerson::class, 'person_a_id');
    }

    public function personB(): BelongsTo
    {
        return $this->belongsTo(EventPerson::class, 'person_b_id');
    }
}
