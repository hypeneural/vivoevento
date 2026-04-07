<?php

namespace App\Modules\FaceSearch\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventFaceSearchRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'requester_type',
        'requester_user_id',
        'status',
        'consent_version',
        'selfie_storage_strategy',
        'faces_detected',
        'query_face_quality_score',
        'query_face_quality_tier',
        'query_face_rejection_reason',
        'top_k',
        'best_distance',
        'result_photo_ids_json',
        'expires_at',
    ];

    protected $casts = [
        'faces_detected' => 'integer',
        'query_face_quality_score' => 'float',
        'query_face_quality_tier' => 'string',
        'query_face_rejection_reason' => 'string',
        'top_k' => 'integer',
        'best_distance' => 'float',
        'result_photo_ids_json' => 'array',
        'expires_at' => 'datetime',
    ];

    protected static function newFactory(): \Database\Factories\EventFaceSearchRequestFactory
    {
        return \Database\Factories\EventFaceSearchRequestFactory::new();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Events\Models\Event::class);
    }

    public function requesterUser(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Users\Models\User::class, 'requester_user_id');
    }
}
