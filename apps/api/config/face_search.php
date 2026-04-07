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
        'compreface' => [
            'base_url' => env('FACE_SEARCH_COMPRE_FACE_BASE_URL', 'http://localhost:8000'),
            'api_key' => env('FACE_SEARCH_COMPRE_FACE_API_KEY', ''),
            'face_plugins' => env('FACE_SEARCH_COMPRE_FACE_FACE_PLUGINS', 'calculator,landmarks'),
            'det_prob_threshold' => env('FACE_SEARCH_COMPRE_FACE_DET_PROB_THRESHOLD'),
            'status' => (bool) env('FACE_SEARCH_COMPRE_FACE_STATUS', true),
            'timeout' => (int) env('FACE_SEARCH_COMPRE_FACE_TIMEOUT', 15),
            'connect_timeout' => (int) env('FACE_SEARCH_COMPRE_FACE_CONNECT_TIMEOUT', 5),
            'provider_version' => env('FACE_SEARCH_COMPRE_FACE_PROVIDER_VERSION', 'compreface-rest-v1'),
            'model' => env('FACE_SEARCH_COMPRE_FACE_MODEL', env('FACE_SEARCH_EMBEDDING_MODEL', 'compreface-face-v1')),
            'model_snapshot' => env('FACE_SEARCH_COMPRE_FACE_MODEL_SNAPSHOT', ''),
            'use_base64' => (bool) env('FACE_SEARCH_COMPRE_FACE_USE_BASE64', true),
        ],
    ],
];
