<?php

namespace App\Modules\FaceSearch\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventFaceSearchSetting extends Model
{
    use HasFactory;

    /**
     * @return array<int, string>
     */
    public static function localAttributeKeys(): array
    {
        return [
            'provider_key',
            'embedding_model_key',
            'vector_store_key',
            'search_strategy',
            'enabled',
            'min_face_size_px',
            'min_quality_score',
            'search_threshold',
            'top_k',
            'allow_public_selfie_search',
            'selfie_retention_hours',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function awsAttributeKeys(): array
    {
        return [
            'recognition_enabled',
            'search_backend_key',
            'fallback_backend_key',
            'routing_policy',
            'shadow_mode_percentage',
            'aws_region',
            'aws_collection_id',
            'aws_collection_arn',
            'aws_face_model_version',
            'aws_search_mode',
            'aws_index_quality_filter',
            'aws_search_faces_quality_filter',
            'aws_search_users_quality_filter',
            'aws_search_face_match_threshold',
            'aws_search_user_match_threshold',
            'aws_associate_user_match_threshold',
            'aws_max_faces_per_image',
            'aws_index_profile_key',
            'aws_detection_attributes_json',
            'delete_remote_vectors_on_event_close',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function configurableAttributeKeys(): array
    {
        return [
            ...self::localAttributeKeys(),
            ...self::awsAttributeKeys(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultAttributes(): array
    {
        /** @var array<string, mixed> $aws */
        $aws = config('face_search.providers.aws_rekognition', []);

        return [
            'provider_key' => (string) config('face_search.default_detection_provider', 'noop'),
            'embedding_model_key' => (string) config('face_search.default_embedding_model', 'face-embedding-foundation-v1'),
            'vector_store_key' => (string) config('face_search.default_vector_store', 'pgvector'),
            'search_strategy' => (string) config('face_search.default_search_strategy', 'exact'),
            'enabled' => false,
            'min_face_size_px' => (int) config('face_search.min_face_size_px', 24),
            'min_quality_score' => (float) config('face_search.min_quality_score', 0.60),
            'search_threshold' => (float) config('face_search.search_threshold', 0.50),
            'top_k' => (int) config('face_search.top_k', 50),
            'allow_public_selfie_search' => false,
            'selfie_retention_hours' => 24,
            'recognition_enabled' => false,
            'search_backend_key' => 'local_pgvector',
            'fallback_backend_key' => null,
            'routing_policy' => 'local_only',
            'shadow_mode_percentage' => 0,
            'aws_region' => (string) ($aws['region'] ?? env('AWS_REGION', env('AWS_DEFAULT_REGION', 'eu-central-1'))),
            'aws_collection_id' => null,
            'aws_collection_arn' => null,
            'aws_face_model_version' => null,
            'aws_search_mode' => 'faces',
            'aws_index_quality_filter' => (string) ($aws['index_quality_filter'] ?? 'AUTO'),
            'aws_search_faces_quality_filter' => (string) ($aws['search_faces_quality_filter'] ?? 'NONE'),
            'aws_search_users_quality_filter' => (string) ($aws['search_users_quality_filter'] ?? 'NONE'),
            'aws_search_face_match_threshold' => (float) ($aws['search_face_match_threshold'] ?? 80),
            'aws_search_user_match_threshold' => (float) ($aws['search_user_match_threshold'] ?? 80),
            'aws_associate_user_match_threshold' => (float) ($aws['associate_user_match_threshold'] ?? 75),
            'aws_max_faces_per_image' => 100,
            'aws_index_profile_key' => 'social_gallery_event',
            'aws_detection_attributes_json' => array_values($aws['detection_attributes'] ?? ['DEFAULT', 'FACE_OCCLUDED']),
            'delete_remote_vectors_on_event_close' => false,
        ];
    }

    protected $fillable = [
        'event_id',
        'provider_key',
        'embedding_model_key',
        'vector_store_key',
        'search_strategy',
        'enabled',
        'min_face_size_px',
        'min_quality_score',
        'search_threshold',
        'top_k',
        'allow_public_selfie_search',
        'selfie_retention_hours',
        'recognition_enabled',
        'search_backend_key',
        'fallback_backend_key',
        'routing_policy',
        'shadow_mode_percentage',
        'aws_region',
        'aws_collection_id',
        'aws_collection_arn',
        'aws_face_model_version',
        'aws_search_mode',
        'aws_index_quality_filter',
        'aws_search_faces_quality_filter',
        'aws_search_users_quality_filter',
        'aws_search_face_match_threshold',
        'aws_search_user_match_threshold',
        'aws_associate_user_match_threshold',
        'aws_max_faces_per_image',
        'aws_index_profile_key',
        'aws_detection_attributes_json',
        'delete_remote_vectors_on_event_close',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'search_strategy' => 'string',
        'min_face_size_px' => 'integer',
        'min_quality_score' => 'float',
        'search_threshold' => 'float',
        'top_k' => 'integer',
        'allow_public_selfie_search' => 'boolean',
        'selfie_retention_hours' => 'integer',
        'recognition_enabled' => 'boolean',
        'search_backend_key' => 'string',
        'fallback_backend_key' => 'string',
        'routing_policy' => 'string',
        'shadow_mode_percentage' => 'integer',
        'aws_region' => 'string',
        'aws_collection_id' => 'string',
        'aws_collection_arn' => 'string',
        'aws_face_model_version' => 'string',
        'aws_search_mode' => 'string',
        'aws_index_quality_filter' => 'string',
        'aws_search_faces_quality_filter' => 'string',
        'aws_search_users_quality_filter' => 'string',
        'aws_search_face_match_threshold' => 'float',
        'aws_search_user_match_threshold' => 'float',
        'aws_associate_user_match_threshold' => 'float',
        'aws_max_faces_per_image' => 'integer',
        'aws_index_profile_key' => 'string',
        'aws_detection_attributes_json' => 'array',
        'delete_remote_vectors_on_event_close' => 'boolean',
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
