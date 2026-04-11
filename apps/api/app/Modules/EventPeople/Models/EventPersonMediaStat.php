<?php

namespace App\Modules\EventPeople\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventPersonMediaStat extends Model
{
    protected $fillable = [
        'event_id',
        'event_person_id',
        'media_count',
        'solo_media_count',
        'with_others_media_count',
        'published_media_count',
        'pending_media_count',
        'best_media_id',
        'latest_media_id',
        'projected_at',
    ];

    protected $casts = [
        'media_count' => 'integer',
        'solo_media_count' => 'integer',
        'with_others_media_count' => 'integer',
        'published_media_count' => 'integer',
        'pending_media_count' => 'integer',
        'projected_at' => 'datetime',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(EventPerson::class, 'event_person_id');
    }
}
