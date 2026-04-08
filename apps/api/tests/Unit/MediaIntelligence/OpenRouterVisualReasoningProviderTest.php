<?php

use App\Modules\Events\Models\Event;
use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;
use App\Modules\MediaIntelligence\Services\OpenAiCompatibleVisualReasoningPayloadFactory;
use App\Modules\MediaIntelligence\Services\OpenRouterVisualReasoningProvider;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Models\EventMediaVariant;
use App\Modules\MediaProcessing\Services\MediaAssetUrlService;
use App\Shared\Support\ExternalImageUrlPolicy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('maps a structured openrouter response into the domain dto and applies the shared multimodal contract', function () {
    $event = Event::factory()->create([
        'title' => 'Casamento OpenRouter',
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'caption' => 'Momento especial',
    ]);

    EventMediaVariant::query()->create([
        'event_media_id' => $media->id,
        'variant_key' => 'fast_preview',
        'disk' => 'public',
        'path' => "events/{$event->id}/fast/{$media->id}.jpg",
        'mime_type' => 'image/jpeg',
        'width' => 512,
        'height' => 512,
        'size_bytes' => 1024,
    ]);

    $settings = EventMediaIntelligenceSetting::factory()->make([
        'event_id' => $event->id,
        'provider_key' => 'openrouter',
        'model_key' => 'openai/gpt-4.1-mini',
        'response_schema_version' => 'foundation-v1',
    ]);

    config()->set('media_intelligence.providers.openrouter.base_url', 'https://openrouter.ai/api/v1');
    config()->set('media_intelligence.providers.openrouter.api_key', 'or-test-key');
    config()->set('media_intelligence.providers.openrouter.model', 'openai/gpt-4.1-mini');
    config()->set('media_intelligence.providers.openrouter.site_url', 'https://eventovivo.test');
    config()->set('media_intelligence.providers.openrouter.app_name', 'Evento Vivo');

    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => Http::response([
            'id' => 'or-test',
            'model' => 'openai/gpt-4.1-mini',
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'decision' => 'approve',
                            'review' => false,
                            'reason' => 'Imagem compatível com o evento.',
                            'short_caption' => 'Registro elegante da festa.',
                            'reply_text' => 'Momento de risadas e lembrancas! 📱🎉',
                            'tags' => ['festa', 'casamento'],
                        ], JSON_THROW_ON_ERROR),
                    ],
                ],
            ],
            'usage' => [
                'prompt_tokens' => 144,
                'completion_tokens' => 33,
            ],
        ]),
    ]);

    $assetUrls = \Mockery::mock(MediaAssetUrlService::class);
    $assetUrls->shouldReceive('preview')
        ->once()
        ->withArgs(fn (EventMedia $arg) => $arg->id === $media->id)
        ->andReturn('https://cdn.eventovivo.test/events/' . $event->id . '/fast/' . $media->id . '.jpg');

    $provider = new OpenRouterVisualReasoningProvider(
        app(\Illuminate\Http\Client\Factory::class),
        $assetUrls,
        app(OpenAiCompatibleVisualReasoningPayloadFactory::class),
        app(ExternalImageUrlPolicy::class),
    );

    $result = $provider->evaluate($media->fresh(['event', 'variants']), $settings);

    expect($result->decision->value)->toBe('approve')
        ->and($result->providerKey)->toBe('openrouter')
        ->and($result->modelKey)->toBe('openai/gpt-4.1-mini')
        ->and($result->shortCaption)->toBe('Registro elegante da festa.')
        ->and($result->replyText)->toBe('Momento de risadas e lembrancas! 📱🎉')
        ->and($result->tokensInput)->toBe(144)
        ->and($result->tokensOutput)->toBe(33);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://openrouter.ai/api/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer or-test-key')
            && $request->hasHeader('HTTP-Referer', 'https://eventovivo.test')
            && $request->hasHeader('X-Title', 'Evento Vivo')
            && ($body['messages'][1]['content'][1]['image_url']['url'] ?? null) !== null
            && ! isset($body['messages'][1]['content'][1]['image_url']['detail'])
            && ! isset($body['messages'][1]['content'][1]['uuid'])
            && ($body['response_format']['type'] ?? null) === 'json_schema';
    });
});

it('falls back to data url for openrouter when no public preview url is available', function () {
    Storage::fake('local');

    $event = Event::factory()->make([
        'title' => 'Casamento OpenRouter Fallback',
    ]);

    $media = EventMedia::factory()->make([
        'id' => 987,
        'event_id' => 321,
        'caption' => null,
        'mime_type' => 'image/jpeg',
        'original_disk' => 'local',
        'original_path' => 'testing/media-intelligence/openrouter-fallback.jpg',
    ]);
    $media->setRelation('event', $event);
    $media->setRelation('variants', collect());

    Storage::disk('local')->put('testing/media-intelligence/openrouter-fallback.jpg', 'fake-image-binary');

    $settings = EventMediaIntelligenceSetting::factory()->make([
        'event_id' => 321,
        'provider_key' => 'openrouter',
        'model_key' => 'openai/gpt-4.1-mini',
        'response_schema_version' => 'foundation-v1',
    ]);

    config()->set('media_intelligence.providers.openrouter.base_url', 'https://openrouter.ai/api/v1');
    config()->set('media_intelligence.providers.openrouter.api_key', 'or-test-key');
    config()->set('media_intelligence.providers.openrouter.model', 'openai/gpt-4.1-mini');
    config()->set('media_intelligence.providers.openrouter.site_url', 'https://eventovivo.test');
    config()->set('media_intelligence.providers.openrouter.app_name', 'Evento Vivo');

    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => Http::response([
            'id' => 'or-test-data-url',
            'model' => 'openai/gpt-4.1-mini',
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'decision' => 'approve',
                        'review' => false,
                        'reason' => 'Imagem compativel.',
                        'short_caption' => 'Legenda validada.',
                        'tags' => ['teste'],
                    ], JSON_THROW_ON_ERROR),
                ],
            ]],
            'usage' => [
                'prompt_tokens' => 88,
                'completion_tokens' => 17,
            ],
        ]),
    ]);

    $assetUrls = \Mockery::mock(MediaAssetUrlService::class);
    $assetUrls->shouldReceive('preview')
        ->once()
        ->withArgs(fn (EventMedia $arg) => $arg->original_path === $media->original_path)
        ->andReturnNull();

    $provider = new OpenRouterVisualReasoningProvider(
        app(\Illuminate\Http\Client\Factory::class),
        $assetUrls,
        app(OpenAiCompatibleVisualReasoningPayloadFactory::class),
        app(ExternalImageUrlPolicy::class),
    );

    $result = $provider->evaluate($media, $settings);

    expect($result->decision->value)->toBe('approve')
        ->and(data_get($result->rawResponse, 'input_path_used'))->toBe('data_url')
        ->and(data_get($result->rawResponse, 'input_source_ref'))->toBe('local:testing/media-intelligence/openrouter-fallback.jpg')
        ->and(data_get($result->rawResponse, 'input_mime_type'))->toBe('image/jpeg');

    Http::assertSent(function ($request) {
        $body = $request->data();
        $url = data_get($body, 'messages.1.content.1.image_url.url', '');

        return $request->url() === 'https://openrouter.ai/api/v1/chat/completions'
            && is_string($url)
            && str_starts_with($url, 'data:image/jpeg;base64,')
            && ! isset($body['messages'][1]['content'][1]['image_url']['detail']);
    });
});

it('falls back to data url for openrouter when preview url is local only', function () {
    Storage::fake('local');

    $event = Event::factory()->make([
        'title' => 'Casamento OpenRouter Localhost',
    ]);

    $media = EventMedia::factory()->make([
        'id' => 988,
        'event_id' => 322,
        'caption' => null,
        'mime_type' => 'image/jpeg',
        'original_disk' => 'local',
        'original_path' => 'testing/media-intelligence/openrouter-localhost.jpg',
    ]);
    $media->setRelation('event', $event);
    $media->setRelation('variants', collect());

    Storage::disk('local')->put('testing/media-intelligence/openrouter-localhost.jpg', 'fake-image-binary');

    $settings = EventMediaIntelligenceSetting::factory()->make([
        'event_id' => 322,
        'provider_key' => 'openrouter',
        'model_key' => 'openai/gpt-4.1-mini',
        'response_schema_version' => 'foundation-v1',
    ]);

    config()->set('media_intelligence.providers.openrouter.base_url', 'https://openrouter.ai/api/v1');
    config()->set('media_intelligence.providers.openrouter.api_key', 'or-test-key');
    config()->set('media_intelligence.providers.openrouter.model', 'openai/gpt-4.1-mini');
    config()->set('media_intelligence.providers.openrouter.site_url', 'https://eventovivo.test');
    config()->set('media_intelligence.providers.openrouter.app_name', 'Evento Vivo');

    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => Http::response([
            'id' => 'or-test-localhost-url',
            'model' => 'openai/gpt-4.1-mini',
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'decision' => 'approve',
                        'review' => false,
                        'reason' => 'Imagem compativel.',
                        'short_caption' => 'Legenda validada.',
                        'reply_text' => 'Resposta curta com emoji! 🎉',
                        'tags' => ['teste'],
                    ], JSON_THROW_ON_ERROR),
                ],
            ]],
            'usage' => [
                'prompt_tokens' => 88,
                'completion_tokens' => 17,
            ],
        ]),
    ]);

    $assetUrls = \Mockery::mock(MediaAssetUrlService::class);
    $assetUrls->shouldReceive('preview')
        ->once()
        ->withArgs(fn (EventMedia $arg) => $arg->original_path === $media->original_path)
        ->andReturn('http://localhost:8000/storage/events/322/variants/988/fast_preview.webp');

    $provider = new OpenRouterVisualReasoningProvider(
        app(\Illuminate\Http\Client\Factory::class),
        $assetUrls,
        app(OpenAiCompatibleVisualReasoningPayloadFactory::class),
        app(ExternalImageUrlPolicy::class),
    );

    $result = $provider->evaluate($media, $settings);

    expect($result->decision->value)->toBe('approve')
        ->and(data_get($result->rawResponse, 'input_path_used'))->toBe('data_url')
        ->and(data_get($result->rawResponse, 'input_source_ref'))->toBe('local:testing/media-intelligence/openrouter-localhost.jpg');

    Http::assertSent(function ($request) {
        $body = $request->data();
        $url = data_get($body, 'messages.1.content.1.image_url.url', '');

        return $request->url() === 'https://openrouter.ai/api/v1/chat/completions'
            && is_string($url)
            && str_starts_with($url, 'data:image/jpeg;base64,');
    });
});
