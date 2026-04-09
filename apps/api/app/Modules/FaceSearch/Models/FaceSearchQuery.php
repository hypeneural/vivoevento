<?php

namespace App\Modules\FaceSearch\Models;

use App\Modules\FaceSearch\Enums\FaceSearchQueryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaceSearchQuery extends Model
{
    use HasFactory;

    protected $table = 'face_search_queries';

    protected $fillable = [
        'event_id',
        'event_face_search_request_id',
        'backend_key',
        'fallback_backend_key',
        'routing_policy',
        'status',
        'query_media_path',
        'query_face_bbox_json',
        'result_count',
        'error_code',
        'error_message',
        'provider_payload_json',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'status' => FaceSearchQueryStatus::class,
        'query_face_bbox_json' => 'array',
        'provider_payload_json' => 'array',
        'result_count' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    protected static function newFactory(): \Database\Factories\FaceSearchQueryFactory
    {
        return \Database\Factories\FaceSearchQueryFactory::new();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Events\Models\Event::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(EventFaceSearchRequest::class, 'event_face_search_request_id');
    }
}
