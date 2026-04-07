<?php

use App\Modules\Events\Models\Event;
use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;
use App\Modules\MediaIntelligence\Services\VllmVisualReasoningProvider;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Models\EventMediaVariant;
use Illuminate\Support\Facades\Http;

it('maps a structured vllm response into the domain dto', function () {
    $event = Event::factory()->create([
        'title' => 'Casamento AI',
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'caption' => 'Noivos entrando na pista',
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
        'provider_key' => 'vllm',
        'model_key' => 'Qwen/Qwen2.5-VL-3B-Instruct',
        'response_schema_version' => 'foundation-v1',
    ]);

    config()->set('media_intelligence.providers.vllm.base_url', 'http://127.0.0.1:8000/v1');
    config()->set('media_intelligence.providers.vllm.api_key', 'EMPTY');
    config()->set('media_intelligence.providers.vllm.model', 'Qwen/Qwen2.5-VL-3B-Instruct');

    Http::fake([
        'http://127.0.0.1:8000/v1/chat/completions' => Http::response([
            'id' => 'chatcmpl-test',
            'model' => 'Qwen/Qwen2.5-VL-3B-Instruct',
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'decision' => 'approve',
                            'review' => false,
                            'reason' => 'Imagem compatível com o contexto do casamento.',
                            'short_caption' => 'Entrada emocionante dos noivos.',
                            'tags' => ['casamento', 'pista', 'noivos'],
                        ], JSON_THROW_ON_ERROR),
                    ],
                ],
            ],
            'usage' => [
                'prompt_tokens' => 222,
                'completion_tokens' => 48,
            ],
        ]),
    ]);

    $result = app(VllmVisualReasoningProvider::class)->evaluate($media->fresh(['event', 'variants']), $settings);

    expect($result->decision->value)->toBe('approve')
        ->and($result->vlmStatus())->toBe('completed')
        ->and($result->shortCaption)->toBe('Entrada emocionante dos noivos.')
        ->and($result->tags)->toBe(['casamento', 'pista', 'noivos'])
        ->and($result->providerKey)->toBe('vllm')
        ->and($result->modelKey)->toBe('Qwen/Qwen2.5-VL-3B-Instruct')
        ->and($result->tokensInput)->toBe(222)
        ->and($result->tokensOutput)->toBe(48);

    Http::assertSent(function ($request) use ($media) {
        $body = $request->data();

        return $request->url() === 'http://127.0.0.1:8000/v1/chat/completions'
            && ($body['response_format']['type'] ?? null) === 'json_schema'
            && ($body['messages'][1]['content'][1]['type'] ?? null) === 'image_url'
            && ($body['messages'][1]['content'][1]['image_url']['url'] ?? null) !== null
            && ! isset($body['messages'][1]['content'][1]['image_url']['detail'])
            && ! isset($body['messages'][1]['content'][1]['uuid']);
    });
});

it('fails when vllm returns invalid json content', function () {
    $event = Event::factory()->create();
    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
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
        'provider_key' => 'vllm',
    ]);

    config()->set('media_intelligence.providers.vllm.base_url', 'http://127.0.0.1:8000/v1');

    Http::fake([
        'http://127.0.0.1:8000/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => 'not-json',
                    ],
                ],
            ],
        ]),
    ]);

    expect(fn () => app(VllmVisualReasoningProvider::class)->evaluate($media->fresh(['event', 'variants']), $settings))
        ->toThrow(RuntimeException::class, 'vLLM returned an invalid JSON payload for media intelligence.');
});
