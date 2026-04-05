<?php

use App\Modules\ContentModeration\Models\EventContentModerationSetting;
use App\Modules\ContentModeration\Services\OpenAiContentModerationProvider;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Support\Facades\Http;

it('maps the openai moderation payload into the internal result dto', function () {
    config()->set('content_moderation.providers.openai.api_key', 'test-key');
    config()->set('content_moderation.providers.openai.model', 'omni-moderation-latest');
    config()->set('content_moderation.providers.openai.model_snapshot', 'omni-moderation-2024-09-26');
    config()->set('content_moderation.providers.openai.provider_version', 'openai-http-v1');

    Http::fake([
        'https://api.openai.com/v1/moderations' => Http::response([
            'id' => 'modr_test',
            'model' => 'omni-moderation-latest',
            'results' => [[
                'flagged' => false,
                'category_scores' => [
                    'sexual' => 0.72,
                    'sexual/minors' => 0.01,
                    'violence' => 0.05,
                    'violence/graphic' => 0.02,
                    'self-harm' => 0.01,
                    'self-harm/intent' => 0.00,
                    'self-harm/instructions' => 0.00,
                ],
            ]],
        ]),
    ]);

    $settings = EventContentModerationSetting::factory()->make([
        'provider_key' => 'openai',
        'review_thresholds_json' => [
            'nudity' => 0.60,
            'violence' => 0.60,
            'self_harm' => 0.60,
        ],
        'hard_block_thresholds_json' => [
            'nudity' => 0.90,
            'violence' => 0.90,
            'self_harm' => 0.90,
        ],
    ]);

    $media = EventMedia::factory()->make([
        'caption' => 'Convidados chegando na festa',
        'original_disk' => 'public',
        'original_path' => 'events/10/originals/photo.jpg',
    ]);

    $result = app(OpenAiContentModerationProvider::class)->evaluate($media, $settings);

    expect($result->decision->value)->toBe('review')
        ->and($result->providerKey)->toBe('openai')
        ->and($result->providerVersion)->toBe('openai-http-v1')
        ->and($result->modelKey)->toBe('omni-moderation-latest')
        ->and($result->modelSnapshot)->toBe('omni-moderation-2024-09-26')
        ->and($result->categoryScores['nudity'])->toBe(0.72)
        ->and($result->reasonCodes)->toContain('nudity.review');

    Http::assertSent(function ($request) {
        $payload = $request->data();

        return $request->url() === 'https://api.openai.com/v1/moderations'
            && $payload['model'] === 'omni-moderation-latest'
            && count($payload['input']) === 2
            && $payload['input'][0]['type'] === 'image_url'
            && $payload['input'][1]['type'] === 'text';
    });
});
