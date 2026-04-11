<?php

namespace App\Modules\EventPeople\Models;

use App\Modules\EventPeople\Enums\EventPersonRepresentativeSyncStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventPersonRepresentativeFace extends Model
{
    protected $fillable = [
        'event_id',
        'event_person_id',
        'event_media_face_id',
        'rank_score',
        'quality_score',
        'pose_bucket',
        'context_hash',
        'sync_status',
        'last_synced_at',
        'sync_payload',
    ];

    protected $casts = [
        'rank_score' => 'float',
        'quality_score' => 'float',
        'sync_status' => EventPersonRepresentativeSyncStatus::class,
        'last_synced_at' => 'datetime',
        'sync_payload' => 'array',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(EventPerson::class, 'event_person_id');
    }

    public function face(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\FaceSearch\Models\EventMediaFace::class, 'event_media_face_id');
    }
}
