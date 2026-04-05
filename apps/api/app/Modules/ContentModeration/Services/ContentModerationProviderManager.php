<?php

namespace App\Modules\ContentModeration\Services;

use App\Modules\ContentModeration\DTOs\ContentSafetyEvaluationResult;
use App\Modules\ContentModeration\Models\EventContentModerationSetting;
use App\Modules\MediaProcessing\Services\ProviderCircuitBreaker;
use App\Modules\MediaProcessing\Models\EventMedia;
use InvalidArgumentException;

class ContentModerationProviderManager implements ContentModerationProviderInterface
{
    /**
     * @param array<string, ContentModerationProviderInterface> $providers
     */
    public function __construct(
        private readonly array $providers,
        private readonly ProviderCircuitBreaker $circuitBreaker,
    ) {}

    public function evaluate(
        EventMedia $media,
        EventContentModerationSetting $settings,
    ): ContentSafetyEvaluationResult {
        $providerKey = $settings->provider_key ?: (string) config('content_moderation.default_provider', 'openai');
        $config = (array) config("content_moderation.providers.{$providerKey}", []);
        $failureThreshold = (int) ($config['circuit_breaker']['failure_threshold'] ?? 0);
        $openSeconds = (int) ($config['circuit_breaker']['open_seconds'] ?? 0);

        return $this->circuitBreaker->call(
            scope: "content-moderation:{$providerKey}",
            failureThreshold: $failureThreshold,
            openSeconds: $openSeconds,
            callback: fn () => $this->resolve($settings->provider_key)->evaluate($media, $settings),
        );
    }

    public function resolve(?string $providerKey): ContentModerationProviderInterface
    {
        $resolvedKey = $providerKey ?: (string) config('content_moderation.default_provider', 'openai');

        if (array_key_exists($resolvedKey, $this->providers)) {
            return $this->providers[$resolvedKey];
        }

        throw new InvalidArgumentException("Unsupported content moderation provider [{$resolvedKey}].");
    }
}
