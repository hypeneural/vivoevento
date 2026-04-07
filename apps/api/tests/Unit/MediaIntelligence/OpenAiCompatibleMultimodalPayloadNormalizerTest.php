<?php

use App\Modules\MediaIntelligence\Services\OpenAiCompatibleMultimodalPayloadNormalizer;

it('normalizes multimodal payloads to the app contract before provider dispatch', function () {
    $payload = [
        'model' => 'openai/gpt-4.1-mini',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'json only',
            ],
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'describe the image',
                        'ignored' => 'value',
                    ],
                    [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => 'https://example.test/a.jpg',
                            'detail' => 'high',
                        ],
                        'uuid' => 'media-123',
                    ],
                    [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => 'https://example.test/b.jpg',
                        ],
                    ],
                ],
            ],
        ],
        'response_format' => [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'eventovivo_media_intelligence',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                ],
                'ignored' => true,
            ],
        ],
    ];

    $normalized = app(OpenAiCompatibleMultimodalPayloadNormalizer::class)->normalize($payload);

    expect($normalized['messages'][1]['content'])->toBe([
        [
            'type' => 'text',
            'text' => 'describe the image',
        ],
        [
            'type' => 'image_url',
            'image_url' => [
                'url' => 'https://example.test/a.jpg',
            ],
        ],
    ])
        ->and($normalized['response_format'])->toBe([
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'eventovivo_media_intelligence',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                ],
            ],
        ]);
});
