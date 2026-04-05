<?php

return [
    'default_detection_provider' => env('FACE_SEARCH_DETECTION_PROVIDER', 'noop'),
    'default_embedding_provider' => env('FACE_SEARCH_EMBEDDING_PROVIDER', 'noop'),
    'default_vector_store' => env('FACE_SEARCH_VECTOR_STORE', 'pgvector'),
    'default_embedding_model' => env('FACE_SEARCH_EMBEDDING_MODEL', 'face-embedding-foundation-v1'),
    'embedding_dimension' => (int) env('FACE_SEARCH_EMBEDDING_DIMENSION', 512),
    'crop_disk' => env('FACE_SEARCH_CROP_DISK', 'ai-private'),
    'min_face_size_px' => (int) env('FACE_SEARCH_MIN_FACE_SIZE_PX', 96),
    'min_quality_score' => (float) env('FACE_SEARCH_MIN_QUALITY_SCORE', 0.60),
    'search_threshold' => (float) env('FACE_SEARCH_SEARCH_THRESHOLD', 0.35),
    'top_k' => (int) env('FACE_SEARCH_TOP_K', 50),

    'providers' => [
        'noop' => [
            'provider_version' => 'foundation-v1',
            'model' => 'noop-face-v1',
            'model_snapshot' => 'noop-face-v1',
        ],
    ],
];
