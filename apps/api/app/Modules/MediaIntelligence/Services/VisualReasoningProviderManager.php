<?php

namespace App\Modules\MediaIntelligence\Services;

use App\Modules\MediaIntelligence\DTOs\VisualReasoningEvaluationResult;
use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;
use App\Modules\MediaProcessing\Services\ProviderCircuitBreaker;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

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

        try {
            return $this->evaluateWithProvider($providerKey, $media, $settings);
        } catch (Throwable $exception) {
            $fallbackProviderKey = $this->fallbackProviderKeyFor($providerKey);

            if (! $fallbackProviderKey || $fallbackProviderKey === $providerKey) {
                throw $exception;
            }

            Log::channel((string) config('observability.queue_log_channel', config('logging.default')))
                ->warning('media_intelligence.provider_fallback', [
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

    private function evaluateWithProvider(
        string $providerKey,
        EventMedia $media,
        EventMediaIntelligenceSetting $settings,
    ): VisualReasoningEvaluationResult {
        $config = (array) config("media_intelligence.providers.{$providerKey}", []);
        $failureThreshold = (int) ($config['circuit_breaker']['failure_threshold'] ?? 0);
        $openSeconds = (int) ($config['circuit_breaker']['open_seconds'] ?? 0);

        return $this->circuitBreaker->call(
            scope: "media-intelligence:{$providerKey}",
            failureThreshold: $failureThreshold,
            openSeconds: $openSeconds,
            callback: fn () => $this->resolve($providerKey)->evaluate($media, $settings),
        );
    }

    private function fallbackProviderKeyFor(string $providerKey): ?string
    {
        $fallbackProviderKey = config("media_intelligence.providers.{$providerKey}.fallback_provider_key");

        return is_string($fallbackProviderKey) && $fallbackProviderKey !== ''
            ? $fallbackProviderKey
            : null;
    }

    private function settingsForProvider(
        EventMediaIntelligenceSetting $settings,
        string $providerKey,
    ): EventMediaIntelligenceSetting {
        $fallbackSettings = clone $settings;
        $fallbackSettings->provider_key = $providerKey;
        $fallbackSettings->model_key = (string) config("media_intelligence.providers.{$providerKey}.model", $fallbackSettings->model_key);

        return $fallbackSettings;
    }
}
