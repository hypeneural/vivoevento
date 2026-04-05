<?php

return [
    'default_provider' => env('MEDIA_INTELLIGENCE_PROVIDER', 'vllm'),

    'providers' => [
        'noop' => [
            'provider_version' => 'foundation-v1',
            'model' => 'noop-vlm-v1',
            'model_snapshot' => 'noop-vlm-v1',
        ],

        'vllm' => [
            'base_url' => env('MEDIA_INTELLIGENCE_VLLM_BASE_URL', 'http://localhost:8000/v1'),
            'api_key' => env('MEDIA_INTELLIGENCE_VLLM_API_KEY'),
            'model' => env('MEDIA_INTELLIGENCE_VLLM_MODEL', 'Qwen/Qwen2.5-VL-3B-Instruct'),
            'model_snapshot' => env('MEDIA_INTELLIGENCE_VLLM_MODEL_SNAPSHOT'),
            'timeout' => (int) env('MEDIA_INTELLIGENCE_VLLM_TIMEOUT', 20),
            'connect_timeout' => (int) env('MEDIA_INTELLIGENCE_VLLM_CONNECT_TIMEOUT', 5),
            'provider_version' => env('MEDIA_INTELLIGENCE_VLLM_PROVIDER_VERSION', 'vllm-openai-v1'),
            'temperature' => (float) env('MEDIA_INTELLIGENCE_VLLM_TEMPERATURE', 0.1),
            'max_completion_tokens' => (int) env('MEDIA_INTELLIGENCE_VLLM_MAX_COMPLETION_TOKENS', 300),
            'circuit_breaker' => [
                'failure_threshold' => (int) env('MEDIA_INTELLIGENCE_VLLM_CIRCUIT_FAILURE_THRESHOLD', 3),
                'open_seconds' => (int) env('MEDIA_INTELLIGENCE_VLLM_CIRCUIT_OPEN_SECONDS', 60),
            ],
        ],
    ],
];
