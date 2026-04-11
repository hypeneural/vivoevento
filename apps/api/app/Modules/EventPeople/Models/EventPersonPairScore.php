<?php

namespace App\Modules\EventPeople\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventPersonPairScore extends Model
{
    protected $fillable = [
        'event_id',
        'person_a_id',
        'person_b_id',
        'person_pair_key',
        'co_media_count',
        'weighted_score',
        'last_seen_together_at',
        'projected_at',
    ];

    protected $casts = [
        'co_media_count' => 'integer',
        'weighted_score' => 'float',
        'last_seen_together_at' => 'datetime',
        'projected_at' => 'datetime',
    ];

    public function personA(): BelongsTo
    {
        return $this->belongsTo(EventPerson::class, 'person_a_id');
    }

    public function personB(): BelongsTo
    {
        return $this->belongsTo(EventPerson::class, 'person_b_id');
    }
}
