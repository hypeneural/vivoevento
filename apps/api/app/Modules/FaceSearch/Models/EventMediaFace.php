<?php

namespace App\Modules\FaceSearch\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventMediaFace extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'event_media_id',
        'face_index',
        'bbox_x',
        'bbox_y',
        'bbox_w',
        'bbox_h',
        'detection_confidence',
        'quality_score',
        'quality_tier',
        'quality_rejection_reason',
        'sharpness_score',
        'face_area_ratio',
        'pose_yaw',
        'pose_pitch',
        'pose_roll',
        'searchable',
        'crop_disk',
        'crop_path',
        'embedding_model_key',
        'embedding_version',
        'vector_store_key',
        'vector_ref',
        'face_hash',
        'is_primary_face_candidate',
        'embedding',
    ];

    protected $casts = [
        'face_index' => 'integer',
        'bbox_x' => 'integer',
        'bbox_y' => 'integer',
        'bbox_w' => 'integer',
        'bbox_h' => 'integer',
        'detection_confidence' => 'float',
        'quality_score' => 'float',
        'quality_tier' => 'string',
        'quality_rejection_reason' => 'string',
        'sharpness_score' => 'float',
        'face_area_ratio' => 'float',
        'pose_yaw' => 'float',
        'pose_pitch' => 'float',
        'pose_roll' => 'float',
        'searchable' => 'boolean',
        'is_primary_face_candidate' => 'boolean',
    ];

    protected static function newFactory(): \Database\Factories\EventMediaFaceFactory
    {
        return \Database\Factories\EventMediaFaceFactory::new();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Events\Models\Event::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\MediaProcessing\Models\EventMedia::class, 'event_media_id');
    }

    public function personAssignments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Modules\EventPeople\Models\EventPersonFaceAssignment::class, 'event_media_face_id');
    }

    public function scopeForEvent($query, int $eventId)
    {
        return $query->where('event_id', $eventId);
    }

    public function scopeSearchable($query, bool $searchable = true)
    {
        return $query->where('searchable', $searchable);
    }
}
