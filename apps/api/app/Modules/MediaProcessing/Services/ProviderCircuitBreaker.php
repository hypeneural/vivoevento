<?php

namespace App\Modules\MediaProcessing\Services;

use App\Shared\Exceptions\ProviderCircuitOpenException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProviderCircuitBreaker
{
    public function __construct(
        private readonly CacheRepository $cache,
    ) {}

    /**
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public function call(
        string $scope,
        int $failureThreshold,
        int $openSeconds,
        callable $callback,
    ): mixed {
        if ($failureThreshold <= 0 || $openSeconds <= 0) {
            return $callback();
        }

        if ($this->isOpen($scope)) {
            throw new ProviderCircuitOpenException("Provider circuit is open for [{$scope}].");
        }

        try {
            $result = $callback();
            $this->reset($scope);

            return $result;
        } catch (Throwable $exception) {
            $this->recordFailure($scope, $failureThreshold, $openSeconds, $exception);

            throw $exception;
        }
    }

    public function isOpen(string $scope): bool
    {
        $openUntil = $this->cache->get($this->openUntilKey($scope));

        if (! is_numeric($openUntil)) {
            return false;
        }

        return (int) $openUntil > now()->getTimestamp();
    }

    public function reset(string $scope): void
    {
        $this->cache->forget($this->failureCountKey($scope));
        $this->cache->forget($this->openUntilKey($scope));
    }

    private function recordFailure(
        string $scope,
        int $failureThreshold,
        int $openSeconds,
        Throwable $exception,
    ): void {
        $failureCount = (int) $this->cache->get($this->failureCountKey($scope), 0) + 1;

        $this->cache->put($this->failureCountKey($scope), $failureCount, now()->addSeconds($openSeconds));

        if ($failureCount < $failureThreshold) {
            return;
        }

        $openUntil = now()->addSeconds($openSeconds)->getTimestamp();

        $this->cache->put($this->openUntilKey($scope), $openUntil, now()->addSeconds($openSeconds));

        Log::channel((string) config('observability.queue_log_channel', config('logging.default')))
            ->warning('provider_circuit.opened', [
                'scope' => $scope,
                'failure_count' => $failureCount,
                'failure_threshold' => $failureThreshold,
                'open_seconds' => $openSeconds,
                'exception_class' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
    }

    private function failureCountKey(string $scope): string
    {
        return "provider-circuit:{$scope}:failures";
    }

    private function openUntilKey(string $scope): string
    {
        return "provider-circuit:{$scope}:open-until";
    }
}
