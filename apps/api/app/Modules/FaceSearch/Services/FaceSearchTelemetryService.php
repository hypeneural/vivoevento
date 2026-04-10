<?php

namespace App\Modules\FaceSearch\Services;

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Models\EventFaceSearchRequest;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\FaceSearch\Models\FaceSearchQuery;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class FaceSearchTelemetryService
{
    /**
     * @param array<string, mixed> $execution
     * @param array<int, mixed> $results
     */
    public function recordQueryCompleted(
        Event $event,
        EventFaceSearchRequest $request,
        ?FaceSearchQuery $query,
        array $execution,
        array $results,
        bool $publicSearch,
    ): void {
        $providerPayload = is_array($execution['provider_payload_json'] ?? null)
            ? $execution['provider_payload_json']
            : [];

        $this->channel()->info('face_search.query.completed', $this->filterNull([
            ...$this->eventPayload($event),
            'face_search_request_id' => $request->id,
            'face_search_query_id' => $query?->id,
            'requester_type' => $request->requester_type,
            'public_search' => $publicSearch,
            'request_status' => $request->status,
            'query_status' => $this->queryStatus($query),
            'query_backend_key' => $query?->backend_key,
            'routing_policy' => $query?->routing_policy,
            'fallback_backend_key' => $query?->fallback_backend_key,
            'primary_backend_key' => $execution['primary_backend_key'] ?? null,
            'response_backend_key' => $execution['response_backend_key'] ?? null,
            'fallback_triggered' => (bool) ($execution['fallback_triggered'] ?? false),
            'search_mode_requested' => $providerPayload['search_mode_requested'] ?? null,
            'search_mode_resolved' => $providerPayload['search_mode_resolved'] ?? null,
            'search_mode_fallback_reason' => $providerPayload['search_mode_fallback_reason'] ?? null,
            'primary_duration_ms' => $execution['primary_duration_ms'] ?? null,
            'response_duration_ms' => $execution['response_duration_ms'] ?? null,
            'result_count' => count($results),
        ]));
    }

    public function recordQueryValidationFailed(
        Event $event,
        EventFaceSearchRequest $request,
        ?FaceSearchQuery $query,
        ValidationException $exception,
        bool $publicSearch,
    ): void {
        $this->channel()->warning('face_search.query.validation_failed', $this->filterNull([
            ...$this->eventPayload($event),
            'face_search_request_id' => $request->id,
            'face_search_query_id' => $query?->id,
            'requester_type' => $request->requester_type,
            'public_search' => $publicSearch,
            'request_status' => $request->status,
            'query_status' => $this->queryStatus($query),
            'query_backend_key' => $query?->backend_key,
            'routing_policy' => $query?->routing_policy,
            'error_code' => 'validation_failed',
            'error_message' => collect($exception->errors())->flatten()->first(),
        ]));
    }

    public function recordQueryFailed(
        Event $event,
        EventFaceSearchRequest $request,
        ?FaceSearchQuery $query,
        Throwable $exception,
        bool $publicSearch,
    ): void {
        $this->channel()->error('face_search.query.failed', $this->filterNull([
            ...$this->eventPayload($event),
            'face_search_request_id' => $request->id,
            'face_search_query_id' => $query?->id,
            'requester_type' => $request->requester_type,
            'public_search' => $publicSearch,
            'request_status' => $request->status,
            'query_status' => $this->queryStatus($query),
            'query_backend_key' => $query?->backend_key,
            'routing_policy' => $query?->routing_policy,
            'error_code' => class_basename($exception),
            'error_message' => $exception->getMessage(),
            'exception_class' => $exception::class,
        ]));
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function recordRouterFallbackTriggered(
        Event $event,
        EventFaceSearchSetting $settings,
        array $payload,
    ): void {
        $this->channel()->warning('face_search.router.fallback_triggered', $this->filterNull([
            ...$this->eventPayload($event),
            'routing_policy' => $settings->routing_policy,
            'search_backend_key' => $settings->search_backend_key,
            'fallback_backend_key' => $settings->fallback_backend_key,
            ...$payload,
        ]));
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function recordRouterShadowFailed(
        Event $event,
        EventFaceSearchSetting $settings,
        array $payload,
    ): void {
        $this->channel()->warning('face_search.router.shadow_failed', $this->filterNull([
            ...$this->eventPayload($event),
            'routing_policy' => $settings->routing_policy,
            'search_backend_key' => $settings->search_backend_key,
            'fallback_backend_key' => $settings->fallback_backend_key,
            ...$payload,
        ]));
    }

    /**
     * @param array<string, mixed> $context
     */
    public function recordAwsOperationFailed(
        string $operation,
        Throwable $exception,
        array $context = [],
    ): void {
        $this->channel()->error('face_search.aws.operation_failed', $this->filterNull([
            'backend_key' => 'aws_rekognition',
            'operation' => $operation,
            'error_code' => method_exists($exception, 'getAwsErrorCode') ? $exception->getAwsErrorCode() : class_basename($exception),
            'error_message' => method_exists($exception, 'getAwsErrorMessage') && is_string($exception->getAwsErrorMessage()) && $exception->getAwsErrorMessage() !== ''
                ? $exception->getAwsErrorMessage()
                : $exception->getMessage(),
            'exception_class' => $exception::class,
            ...$context,
        ]));
    }

    /**
     * @return array<string, int|string|null>
     */
    private function eventPayload(Event $event): array
    {
        return [
            'event_id' => $event->id,
            'event_slug' => $event->slug,
            'event_title' => $event->title,
        ];
    }

    private function queryStatus(?FaceSearchQuery $query): ?string
    {
        if (! $query) {
            return null;
        }

        return $query->status?->value ?? (is_string($query->status) ? $query->status : null);
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
        return Log::channel((string) config('observability.queue_log_channel', config('logging.default')));
    }
}
