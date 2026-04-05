<?php

return [
    'default_provider' => env('CONTENT_MODERATION_PROVIDER', 'openai'),

    'providers' => [
        'noop' => [
            'provider_version' => 'foundation-v1',
            'model' => 'noop-safety-v1',
            'model_snapshot' => 'noop-safety-v1',
        ],

        'openai' => [
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'api_key' => env('OPENAI_API_KEY'),
            'organization' => env('OPENAI_ORGANIZATION'),
            'project' => env('OPENAI_PROJECT'),
            'model' => env('CONTENT_MODERATION_OPENAI_MODEL', 'omni-moderation-latest'),
            'model_snapshot' => env('CONTENT_MODERATION_OPENAI_MODEL_SNAPSHOT'),
            'timeout' => (int) env('CONTENT_MODERATION_OPENAI_TIMEOUT', 15),
            'connect_timeout' => (int) env('CONTENT_MODERATION_OPENAI_CONNECT_TIMEOUT', 5),
            'provider_version' => env('CONTENT_MODERATION_OPENAI_PROVIDER_VERSION', 'openai-http-v1'),
            'circuit_breaker' => [
                'failure_threshold' => (int) env('CONTENT_MODERATION_OPENAI_CIRCUIT_FAILURE_THRESHOLD', 3),
                'open_seconds' => (int) env('CONTENT_MODERATION_OPENAI_CIRCUIT_OPEN_SECONDS', 60),
            ],
        ],
    ],
];
