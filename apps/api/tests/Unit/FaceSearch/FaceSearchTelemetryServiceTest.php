<?php

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Enums\FaceSearchQueryStatus;
use App\Modules\FaceSearch\Models\EventFaceSearchRequest;
use App\Modules\FaceSearch\Models\FaceSearchQuery;
use App\Modules\FaceSearch\Services\FaceSearchTelemetryService;
use Illuminate\Support\Facades\Log;

it('logs shadow divergence summary when query completes', function () {
    config()->set('observability.queue_log_channel', 'stack');

    Log::shouldReceive('channel')
        ->once()
        ->with('stack')
        ->andReturnSelf();

    Log::shouldReceive('info')
        ->once()
        ->withArgs(function (string $message, array $payload): bool {
            expect($message)->toBe('face_search.query.completed');
            expect($payload)->toMatchArray([
                'event_id' => 368,
                'face_search_request_id' => 82,
                'face_search_query_id' => 61,
                'requester_type' => 'user',
                'public_search' => false,
                'query_backend_key' => 'aws_rekognition',
                'routing_policy' => 'aws_primary_local_shadow',
                'fallback_backend_key' => 'local_pgvector',
                'primary_backend_key' => 'aws_rekognition',
                'response_backend_key' => 'aws_rekognition',
                'result_count' => 0,
                'shadow_backend_key' => 'local_pgvector',
                'shadow_status' => 'completed',
                'shadow_result_count' => 1,
                'shadow_latency_ms' => 94,
                'shadow_shared_count' => 0,
                'shadow_top_match_same' => false,
                'shadow_divergence_ratio' => 1.0,
                'shadow_found_when_primary_empty' => true,
            ]);

            return true;
        });

    $event = new Event;
    $event->forceFill([
        'id' => 368,
        'slug' => 'codex-facesearch-vipsocial-20260410-144855',
        'title' => 'Codex FaceSearch VIPSocial 20260410-144855',
    ]);

    $request = new EventFaceSearchRequest;
    $request->forceFill([
        'id' => 82,
        'requester_type' => 'user',
        'status' => 'completed',
    ]);

    $query = new FaceSearchQuery;
    $query->forceFill([
        'id' => 61,
        'backend_key' => 'aws_rekognition',
        'fallback_backend_key' => 'local_pgvector',
        'routing_policy' => 'aws_primary_local_shadow',
        'status' => FaceSearchQueryStatus::Completed,
    ]);

    app(FaceSearchTelemetryService::class)->recordQueryCompleted(
        event: $event,
        request: $request,
        query: $query,
        execution: [
            'primary_backend_key' => 'aws_rekognition',
            'response_backend_key' => 'aws_rekognition',
            'fallback_triggered' => false,
            'primary_duration_ms' => 2718,
            'response_duration_ms' => 2718,
            'provider_payload_json' => [
                'search_mode_requested' => 'faces',
                'search_mode_resolved' => 'faces',
            ],
            'shadow' => [
                'backend_key' => 'local_pgvector',
                'status' => 'completed',
                'result_count' => 1,
                'latency_ms' => 94,
                'comparison' => [
                    'shared_count' => 0,
                    'top_match_same' => false,
                    'divergence_ratio' => 1.0,
                ],
            ],
        ],
        results: [],
        publicSearch: false,
    );
});
