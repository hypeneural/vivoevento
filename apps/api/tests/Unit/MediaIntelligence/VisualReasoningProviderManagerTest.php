<?php

use App\Modules\MediaIntelligence\DTOs\VisualReasoningEvaluationResult;
use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;
use App\Modules\MediaIntelligence\Services\VisualReasoningProviderInterface;
use App\Modules\MediaIntelligence\Services\VisualReasoningProviderManager;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Services\ProviderCircuitBreaker;

it('falls back to the configured vlm provider when the primary provider fails', function () {
    config()->set('media_intelligence.providers.primary.circuit_breaker.failure_threshold', 0);
    config()->set('media_intelligence.providers.primary.circuit_breaker.open_seconds', 0);
    config()->set('media_intelligence.providers.primary.fallback_provider_key', 'fallback');
    config()->set('media_intelligence.providers.fallback.circuit_breaker.failure_threshold', 0);
    config()->set('media_intelligence.providers.fallback.circuit_breaker.open_seconds', 0);
    config()->set('media_intelligence.providers.fallback.model', 'fallback-vlm-model');

    $primary = new class implements VisualReasoningProviderInterface
    {
        public function evaluate(EventMedia $media, EventMediaIntelligenceSetting $settings): VisualReasoningEvaluationResult
        {
            throw new RuntimeException('primary vlm timeout');
        }
    };

    $fallback = new class implements VisualReasoningProviderInterface
    {
        public function evaluate(EventMedia $media, EventMediaIntelligenceSetting $settings): VisualReasoningEvaluationResult
        {
            return VisualReasoningEvaluationResult::approve(
                reason: 'Fallback provider approved the image.',
                shortCaption: 'Legenda de fallback.',
                rawResponse: ['provider' => 'fallback'],
                providerKey: 'fallback',
                providerVersion: 'fallback-v1',
                modelKey: $settings->model_key,
                modelSnapshot: $settings->model_key,
                promptVersion: $settings->prompt_version,
                responseSchemaVersion: $settings->response_schema_version,
                modeApplied: $settings->mode,
            );
        }
    };

    $manager = new VisualReasoningProviderManager(
        providers: [
            'primary' => $primary,
            'fallback' => $fallback,
            'noop' => $fallback,
        ],
        circuitBreaker: app(ProviderCircuitBreaker::class),
    );

    $settings = EventMediaIntelligenceSetting::factory()->make([
        'provider_key' => 'primary',
        'model_key' => 'primary-vlm-model',
    ]);

    $result = $manager->evaluate(EventMedia::factory()->make(), $settings);

    expect($result->providerKey)->toBe('fallback')
        ->and($result->modelKey)->toBe('fallback-vlm-model')
        ->and(data_get($result->rawResponse, 'execution.used_provider'))->toBe('fallback')
        ->and(data_get($result->rawResponse, 'execution.fallback_from'))->toBe('primary')
        ->and(data_get($result->rawResponse, 'execution.reason'))->toBe('provider_exception');
});
