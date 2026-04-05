<?php

namespace App\Modules\MediaIntelligence\Services;

use App\Modules\MediaIntelligence\DTOs\VisualReasoningEvaluationResult;
use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;
use App\Modules\MediaProcessing\Services\ProviderCircuitBreaker;
use App\Modules\MediaProcessing\Models\EventMedia;
use InvalidArgumentException;

class VisualReasoningProviderManager implements VisualReasoningProviderInterface
{
    /**
     * @param array<string, VisualReasoningProviderInterface> $providers
     */
    public function __construct(
        private readonly array $providers,
        private readonly ProviderCircuitBreaker $circuitBreaker,
    ) {}

    public function evaluate(
        EventMedia $media,
        EventMediaIntelligenceSetting $settings,
    ): VisualReasoningEvaluationResult {
        $providerKey = $settings->provider_key ?: (string) config('media_intelligence.default_provider', 'vllm');
        $config = (array) config("media_intelligence.providers.{$providerKey}", []);
        $failureThreshold = (int) ($config['circuit_breaker']['failure_threshold'] ?? 0);
        $openSeconds = (int) ($config['circuit_breaker']['open_seconds'] ?? 0);

        return $this->circuitBreaker->call(
            scope: "media-intelligence:{$providerKey}",
            failureThreshold: $failureThreshold,
            openSeconds: $openSeconds,
            callback: fn () => $this->resolve($settings->provider_key)->evaluate($media, $settings),
        );
    }

    public function resolve(?string $providerKey): VisualReasoningProviderInterface
    {
        $key = $providerKey ?: (string) config('media_intelligence.default_provider', 'vllm');

        if (array_key_exists($key, $this->providers)) {
            return $this->providers[$key];
        }

        if (array_key_exists('noop', $this->providers)) {
            return $this->providers['noop'];
        }

        throw new InvalidArgumentException("Media intelligence provider [{$key}] is not registered.");
    }
}
