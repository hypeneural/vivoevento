<?php

namespace App\Modules\MediaProcessing\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;

class ModerationObservabilityService
{
    /**
     * @param array<string, mixed> $payload
     */
    public function recordFeedResponse(array $payload): void
    {
        $this->channel()->info('moderation.feed.response', $this->filterNull($payload));
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function recordStatsResponse(array $payload): void
    {
        $this->channel()->info('moderation.feed.stats', $this->filterNull($payload));
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function recordDetailResponse(array $payload): void
    {
        $this->channel()->info('moderation.feed.detail', $this->filterNull($payload));
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function recordClientTelemetry(?Authenticatable $actor, ?int $organizationId, array $payload): void
    {
        $this->channel()->info('moderation.feed.client_telemetry', $this->filterNull([
            'user_id' => $actor?->getAuthIdentifier(),
            'organization_id' => $organizationId,
            ...$payload,
        ]));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function filterNull(array $payload): array
    {
        return array_filter($payload, static fn (mixed $value): bool => $value !== null);
    }

    private function channel(): \Illuminate\Log\LogManager|\Psr\Log\LoggerInterface
    {
        return Log::channel((string) config('observability.moderation_log_channel', config('logging.default')));
    }
}
