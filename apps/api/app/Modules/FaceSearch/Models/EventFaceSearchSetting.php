<?php

namespace App\Modules\FaceSearch\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventFaceSearchSetting extends Model
{
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    public static function defaultAttributes(): array
    {
        return [
            'provider_key' => (string) config('face_search.default_detection_provider', 'noop'),
            'embedding_model_key' => (string) config('face_search.default_embedding_model', 'face-embedding-foundation-v1'),
            'vector_store_key' => (string) config('face_search.default_vector_store', 'pgvector'),
            'enabled' => false,
            'min_face_size_px' => (int) config('face_search.min_face_size_px', 96),
            'min_quality_score' => (float) config('face_search.min_quality_score', 0.60),
            'search_threshold' => (float) config('face_search.search_threshold', 0.35),
            'top_k' => (int) config('face_search.top_k', 50),
            'allow_public_selfie_search' => false,
            'selfie_retention_hours' => 24,
        ];
    }

    protected $fillable = [
        'event_id',
        'provider_key',
        'embedding_model_key',
        'vector_store_key',
        'enabled',
        'min_face_size_px',
        'min_quality_score',
        'search_threshold',
        'top_k',
        'allow_public_selfie_search',
        'selfie_retention_hours',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'min_face_size_px' => 'integer',
        'min_quality_score' => 'float',
        'search_threshold' => 'float',
        'top_k' => 'integer',
        'allow_public_selfie_search' => 'boolean',
        'selfie_retention_hours' => 'integer',
    ];

    protected static function newFactory(): \Database\Factories\EventFaceSearchSettingFactory
    {
        return \Database\Factories\EventFaceSearchSettingFactory::new();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Events\Models\Event::class);
    }
}
