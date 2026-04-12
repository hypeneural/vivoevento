<?php

namespace App\Modules\EventPeople\Models;

use App\Modules\EventPeople\Enums\EventPersonReferencePhotoPurpose;
use App\Modules\EventPeople\Enums\EventPersonReferencePhotoSource;
use App\Modules\EventPeople\Enums\EventPersonReferencePhotoStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventPersonReferencePhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'event_person_id',
        'source',
        'event_media_id',
        'event_media_face_id',
        'reference_upload_media_id',
        'purpose',
        'status',
        'quality_score',
        'is_primary_avatar',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'source' => EventPersonReferencePhotoSource::class,
        'purpose' => EventPersonReferencePhotoPurpose::class,
        'status' => EventPersonReferencePhotoStatus::class,
        'quality_score' => 'float',
        'is_primary_avatar' => 'boolean',
    ];

    protected static function newFactory(): \Database\Factories\EventPersonReferencePhotoFactory
    {
        return \Database\Factories\EventPersonReferencePhotoFactory::new();
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(EventPerson::class, 'event_person_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\MediaProcessing\Models\EventMedia::class, 'event_media_id');
    }

    public function face(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\FaceSearch\Models\EventMediaFace::class, 'event_media_face_id');
    }

    public function uploadMedia(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\MediaProcessing\Models\EventMedia::class, 'reference_upload_media_id');
    }
}
