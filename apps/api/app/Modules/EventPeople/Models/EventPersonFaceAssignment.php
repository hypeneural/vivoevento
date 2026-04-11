<?php

namespace App\Modules\EventPeople\Models;

use App\Modules\EventPeople\Enums\EventPersonAssignmentSource;
use App\Modules\EventPeople\Enums\EventPersonAssignmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventPersonFaceAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'event_person_id',
        'event_media_face_id',
        'source',
        'confidence',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'source' => EventPersonAssignmentSource::class,
        'status' => EventPersonAssignmentStatus::class,
        'confidence' => 'float',
        'reviewed_at' => 'datetime',
    ];

    protected static function newFactory(): \Database\Factories\EventPersonFaceAssignmentFactory
    {
        return \Database\Factories\EventPersonFaceAssignmentFactory::new();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Events\Models\Event::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(EventPerson::class, 'event_person_id');
    }

    public function face(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\FaceSearch\Models\EventMediaFace::class, 'event_media_face_id');
    }
}
