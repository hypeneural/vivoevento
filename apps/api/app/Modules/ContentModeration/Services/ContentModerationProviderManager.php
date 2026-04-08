<?php

namespace App\Modules\ContentModeration\Services;

use App\Modules\ContentModeration\DTOs\ContentSafetyEvaluationResult;
use App\Modules\ContentModeration\Models\EventContentModerationSetting;
use App\Modules\MediaProcessing\Services\ProviderCircuitBreaker;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

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

        try {
            return $this->evaluateWithProvider($providerKey, $media, $settings);
        } catch (Throwable $exception) {
            $fallbackProviderKey = $this->fallbackProviderKeyFor($providerKey);

            if (! $fallbackProviderKey || $fallbackProviderKey === $providerKey) {
                throw $exception;
            }

            Log::channel((string) config('observability.queue_log_channel', config('logging.default')))
                ->warning('content_moderation.provider_fallback', [
                    'event_media_id' => $media->id,
                    'primary_provider' => $providerKey,
                    'fallback_provider' => $fallbackProviderKey,
                    'exception_class' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);

            $fallbackResult = $this->evaluateWithProvider(
                $fallbackProviderKey,
                $media,
                $this->settingsForProvider($settings, $fallbackProviderKey),
            );

            return $fallbackResult->withExecutionMetadata([
                'used_provider' => $fallbackProviderKey,
                'fallback_from' => $providerKey,
                'reason' => 'provider_exception',
                'exception_class' => $exception::class,
            ]);
        }
    }

    public function resolve(?string $providerKey): ContentModerationProviderInterface
    {
        $resolvedKey = $providerKey ?: (string) config('content_moderation.default_provider', 'openai');

        if (array_key_exists($resolvedKey, $this->providers)) {
            return $this->providers[$resolvedKey];
        }

        throw new InvalidArgumentException("Unsupported content moderation provider [{$resolvedKey}].");
    }

    private function evaluateWithProvider(
        string $providerKey,
        EventMedia $media,
        EventContentModerationSetting $settings,
    ): ContentSafetyEvaluationResult {
        $config = (array) config("content_moderation.providers.{$providerKey}", []);
        $failureThreshold = (int) ($config['circuit_breaker']['failure_threshold'] ?? 0);
        $openSeconds = (int) ($config['circuit_breaker']['open_seconds'] ?? 0);

        return $this->circuitBreaker->call(
            scope: "content-moderation:{$providerKey}",
            failureThreshold: $failureThreshold,
            openSeconds: $openSeconds,
            callback: fn () => $this->resolve($providerKey)->evaluate($media, $settings),
        );
    }

    private function fallbackProviderKeyFor(string $providerKey): ?string
    {
        $fallbackProviderKey = config("content_moderation.providers.{$providerKey}.fallback_provider_key");

        return is_string($fallbackProviderKey) && $fallbackProviderKey !== ''
            ? $fallbackProviderKey
            : null;
    }

    private function settingsForProvider(
        EventContentModerationSetting $settings,
        string $providerKey,
    ): EventContentModerationSetting {
        $fallbackSettings = clone $settings;
        $fallbackSettings->provider_key = $providerKey;

        return $fallbackSettings;
    }
}
