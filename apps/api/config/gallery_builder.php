<?php

return [
    'ai' => [
        'provider_key' => env('GALLERY_BUILDER_AI_PROVIDER', 'local-guardrailed'),
        'model_key' => env('GALLERY_BUILDER_AI_MODEL', 'gallery-builder-local-v1'),
        'response_schema_version' => 1,
        'temperature' => 0.2,
        'max_completion_tokens' => 1200,
    ],
];
