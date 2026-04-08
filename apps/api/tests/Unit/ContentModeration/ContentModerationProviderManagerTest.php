<?php

use App\Modules\ContentModeration\DTOs\ContentSafetyEvaluationResult;
use App\Modules\ContentModeration\Models\EventContentModerationSetting;
use App\Modules\ContentModeration\Services\ContentModerationProviderInterface;
use App\Modules\ContentModeration\Services\ContentModerationProviderManager;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Services\ProviderCircuitBreaker;

it('falls back to the configured safety provider when the primary provider fails', function () {
    config()->set('content_moderation.providers.primary.circuit_breaker.failure_threshold', 0);
    config()->set('content_moderation.providers.primary.circuit_breaker.open_seconds', 0);
    config()->set('content_moderation.providers.primary.fallback_provider_key', 'fallback');
    config()->set('content_moderation.providers.fallback.circuit_breaker.failure_threshold', 0);
    config()->set('content_moderation.providers.fallback.circuit_breaker.open_seconds', 0);

    $primary = new class implements ContentModerationProviderInterface
    {
        public function evaluate(EventMedia $media, EventContentModerationSetting $settings): ContentSafetyEvaluationResult
        {
            throw new RuntimeException('primary safety timeout');
        }
    };

    $fallback = new class implements ContentModerationProviderInterface
    {
        public function evaluate(EventMedia $media, EventContentModerationSetting $settings): ContentSafetyEvaluationResult
        {
            return ContentSafetyEvaluationResult::pass(
                categoryScores: ['nudity' => 0.01],
                rawResponse: ['provider' => 'fallback'],
                providerKey: 'fallback',
                providerVersion: 'fallback-v1',
                modelKey: 'fallback-safety-model',
                modelSnapshot: 'fallback-safety-model@1',
                thresholdVersion: $settings->threshold_version,
            );
        }
    };

    $manager = new ContentModerationProviderManager(
        providers: [
            'primary' => $primary,
            'fallback' => $fallback,
        ],
        circuitBreaker: app(ProviderCircuitBreaker::class),
    );

    $settings = EventContentModerationSetting::factory()->make([
        'provider_key' => 'primary',
    ]);

    $result = $manager->evaluate(EventMedia::factory()->make(), $settings);

    expect($result->providerKey)->toBe('fallback')
        ->and(data_get($result->rawResponse, 'execution.used_provider'))->toBe('fallback')
        ->and(data_get($result->rawResponse, 'execution.fallback_from'))->toBe('primary')
        ->and(data_get($result->rawResponse, 'execution.reason'))->toBe('provider_exception');
});
