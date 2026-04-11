<?php

namespace App\Modules\EventPeople\Models;

use App\Modules\EventPeople\Enums\EventPersonReviewQueueStatus;
use App\Modules\EventPeople\Enums\EventPersonReviewQueueType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventPersonReviewQueueItem extends Model
{
    protected $table = 'event_person_review_queue';

    protected $fillable = [
        'event_id',
        'queue_key',
        'type',
        'status',
        'priority',
        'event_person_id',
        'event_media_face_id',
        'payload',
        'last_signal_at',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'type' => EventPersonReviewQueueType::class,
        'status' => EventPersonReviewQueueStatus::class,
        'priority' => 'integer',
        'payload' => 'array',
        'last_signal_at' => 'datetime',
        'resolved_at' => 'datetime',
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
