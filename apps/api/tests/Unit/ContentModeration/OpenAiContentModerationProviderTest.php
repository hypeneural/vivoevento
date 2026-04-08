<?php

use App\Modules\ContentModeration\Models\EventContentModerationSetting;
use App\Modules\ContentModeration\Support\ContentSafetyThresholdEvaluator;
use App\Modules\ContentModeration\Services\OpenAiContentModerationProvider;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Services\MediaAssetUrlService;
use App\Shared\Support\ExternalImageUrlPolicy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

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
                'categories' => [
                    'sexual' => true,
                    'violence' => false,
                ],
                'category_scores' => [
                    'sexual' => 0.72,
                    'sexual/minors' => 0.01,
                    'violence' => 0.05,
                    'violence/graphic' => 0.02,
                    'self-harm' => 0.01,
                    'self-harm/intent' => 0.00,
                    'self-harm/instructions' => 0.00,
                ],
                'category_applied_input_types' => [
                    'sexual' => ['image'],
                    'violence' => ['text'],
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

    $assetUrls = \Mockery::mock(MediaAssetUrlService::class);
    $assetUrls->shouldReceive('preview')
        ->once()
        ->withArgs(fn (EventMedia $arg) => $arg === $media)
        ->andReturn('https://cdn.eventovivo.test/events/10/originals/photo.jpg');

    $provider = new OpenAiContentModerationProvider(
        app(\Illuminate\Http\Client\Factory::class),
        $assetUrls,
        app(ContentSafetyThresholdEvaluator::class),
        app(ExternalImageUrlPolicy::class),
    );

    $result = $provider->evaluate($media, $settings);

    expect($result->decision->value)->toBe('review')
        ->and($result->providerKey)->toBe('openai')
        ->and($result->providerVersion)->toBe('openai-http-v1')
        ->and($result->modelKey)->toBe('omni-moderation-latest')
        ->and($result->modelSnapshot)->toBe('omni-moderation-2024-09-26')
        ->and($result->categoryScores['nudity'])->toBe(0.72)
        ->and($result->providerCategories['sexual'])->toBeTrue()
        ->and($result->providerCategoryScores['sexual'])->toBe(0.72)
        ->and($result->providerCategoryInputTypes['sexual'])->toBe(['image'])
        ->and(data_get($result->normalizedProvider, 'input_path_used'))->toBe('image_url')
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

it('falls back to data url when no public preview url is available', function () {
    Storage::fake('public');

    config()->set('content_moderation.providers.openai.api_key', 'test-key');
    config()->set('content_moderation.providers.openai.model', 'omni-moderation-latest');

    Http::fake([
        'https://api.openai.com/v1/moderations' => Http::response([
            'id' => 'modr_data_url',
            'model' => 'omni-moderation-latest',
            'results' => [[
                'flagged' => false,
                'categories' => [
                    'violence' => false,
                ],
                'category_scores' => [
                    'sexual' => 0.02,
                    'violence' => 0.04,
                    'self-harm' => 0.00,
                ],
                'category_applied_input_types' => [
                    'violence' => ['image'],
                ],
            ]],
        ]),
    ]);

    $media = EventMedia::factory()->create([
        'mime_type' => 'image/jpeg',
        'original_disk' => 'public',
        'original_path' => 'events/10/originals/fallback.jpg',
    ]);

    $media->variants()->create([
        'variant_key' => 'fast_preview',
        'disk' => 'public',
        'path' => "events/{$media->event_id}/variants/{$media->id}/fast_preview.webp",
        'mime_type' => 'image/webp',
    ]);

    Storage::disk('public')->put(
        "events/{$media->event_id}/variants/{$media->id}/fast_preview.webp",
        'fake-image-binary'
    );

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

    $assetUrls = \Mockery::mock(MediaAssetUrlService::class);
    $assetUrls->shouldReceive('preview')
        ->once()
        ->withArgs(fn (EventMedia $arg) => $arg->is($media))
        ->andReturnNull();

    $provider = new OpenAiContentModerationProvider(
        app(\Illuminate\Http\Client\Factory::class),
        $assetUrls,
        app(ContentSafetyThresholdEvaluator::class),
        app(ExternalImageUrlPolicy::class),
    );

    $result = $provider->evaluate($media->fresh('variants'), $settings);

    expect($result->decision->value)->toBe('pass')
        ->and(data_get($result->normalizedProvider, 'input_path_used'))->toBe('data_url')
        ->and(data_get($result->rawResponse, 'input_path_used'))->toBe('data_url');

    Http::assertSent(function ($request) {
        $payload = $request->data();
        $url = data_get($payload, 'input.0.image_url.url', '');

        return $request->url() === 'https://api.openai.com/v1/moderations'
            && data_get($payload, 'input.0.type') === 'image_url'
            && is_string($url)
            && str_starts_with($url, 'data:image/webp;base64,');
    });
});

it('falls back to data url when preview url is local only', function () {
    Storage::fake('public');

    config()->set('content_moderation.providers.openai.api_key', 'test-key');
    config()->set('content_moderation.providers.openai.model', 'omni-moderation-latest');

    Http::fake([
        'https://api.openai.com/v1/moderations' => Http::response([
            'id' => 'modr_localhost_url',
            'model' => 'omni-moderation-latest',
            'results' => [[
                'flagged' => false,
                'categories' => [
                    'violence' => false,
                ],
                'category_scores' => [
                    'sexual' => 0.02,
                    'violence' => 0.04,
                    'self-harm' => 0.00,
                ],
                'category_applied_input_types' => [
                    'violence' => ['image'],
                ],
            ]],
        ]),
    ]);

    $media = EventMedia::factory()->create([
        'mime_type' => 'image/jpeg',
        'original_disk' => 'public',
        'original_path' => 'events/10/originals/fallback-localhost.jpg',
    ]);

    $media->variants()->create([
        'variant_key' => 'fast_preview',
        'disk' => 'public',
        'path' => "events/{$media->event_id}/variants/{$media->id}/fast_preview.webp",
        'mime_type' => 'image/webp',
    ]);

    Storage::disk('public')->put(
        "events/{$media->event_id}/variants/{$media->id}/fast_preview.webp",
        'fake-image-binary'
    );

    $settings = EventContentModerationSetting::factory()->make([
        'provider_key' => 'openai',
    ]);

    $assetUrls = \Mockery::mock(MediaAssetUrlService::class);
    $assetUrls->shouldReceive('preview')
        ->once()
        ->withArgs(fn (EventMedia $arg) => $arg->is($media))
        ->andReturn('http://localhost:8000/storage/events/10/variants/1/fast_preview.webp');

    $provider = new OpenAiContentModerationProvider(
        app(\Illuminate\Http\Client\Factory::class),
        $assetUrls,
        app(ContentSafetyThresholdEvaluator::class),
        app(ExternalImageUrlPolicy::class),
    );

    $result = $provider->evaluate($media->fresh('variants'), $settings);

    expect($result->decision->value)->toBe('pass')
        ->and(data_get($result->normalizedProvider, 'input_path_used'))->toBe('data_url')
        ->and(data_get($result->rawResponse, 'input_path_used'))->toBe('data_url');

    Http::assertSent(function ($request) {
        $payload = $request->data();
        $url = data_get($payload, 'input.0.image_url.url', '');

        return $request->url() === 'https://api.openai.com/v1/moderations'
            && is_string($url)
            && str_starts_with($url, 'data:image/webp;base64,');
    });
});
