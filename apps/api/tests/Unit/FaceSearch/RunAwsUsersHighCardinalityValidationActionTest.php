<?php

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Actions\RunAwsUsersHighCardinalityValidationAction;
use App\Modules\FaceSearch\Actions\RunEventFaceSearchHealthCheckAction;
use App\Modules\FaceSearch\Actions\SearchFacesBySelfieAction;
use App\Modules\FaceSearch\Models\EventFaceSearchRequest;
use App\Modules\FaceSearch\Models\FaceSearchQuery;
use App\Modules\FaceSearch\Services\AwsRekognitionFaceSearchBackend;
use App\Modules\FaceSearch\Services\AwsUserHighCardinalityProbeBuilder;
use App\Modules\FaceSearch\Services\AwsUserVectorReadinessService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Mockery as m;

it('runs high-cardinality users validation and aggregates objective match, fallback and latency criteria', function () {
    $event = Event::factory()->active()->create([
        'title' => 'FaceSearch users validation',
    ]);

    \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'recognition_enabled' => true,
        'search_backend_key' => 'aws_rekognition',
        'fallback_backend_key' => 'local_pgvector',
        'routing_policy' => 'aws_primary_local_fallback',
        'aws_search_mode' => 'users',
        'shadow_mode_percentage' => 0,
    ]);

    $probeDirectory = storage_path('app/testing/face-search-users-high-cardinality/action');
    File::deleteDirectory($probeDirectory);
    File::ensureDirectoryExists($probeDirectory);

    $probePathA = $probeDirectory . DIRECTORY_SEPARATOR . 'probe-a.jpg';
    $probePathB = $probeDirectory . DIRECTORY_SEPARATOR . 'probe-b.jpg';
    copy(UploadedFile::fake()->image('probe-a.jpg', 320, 320)->size(180)->getPathname(), $probePathA);
    copy(UploadedFile::fake()->image('probe-b.jpg', 320, 320)->size(180)->getPathname(), $probePathB);

    $healthCheck = m::mock(RunEventFaceSearchHealthCheckAction::class);
    $healthCheck->shouldReceive('execute')
        ->twice()
        ->andReturn([
            'backend_key' => 'aws_rekognition',
            'status' => 'healthy',
            'checks' => [
                'identity' => 'ok',
                'collection' => 'ok',
                'list_faces' => 'ok',
            ],
        ]);

    $backend = m::mock(AwsRekognitionFaceSearchBackend::class);
    $backend->shouldReceive('reconcileCollection')
        ->twice()
        ->andReturn([
            'backend_key' => 'aws_rekognition',
            'collection_id' => 'eventovivo-face-search-event-users-validation',
            'remote_face_count' => 8,
            'local_face_count_before' => 8,
            'matched_records' => 8,
            'restored_records' => 0,
            'remote_only_records_created' => 0,
            'local_only_records_soft_deleted' => 0,
        ]);

    $readiness = m::mock(AwsUserVectorReadinessService::class);
    $readiness->shouldReceive('evaluate')
        ->once()
        ->andReturn([
            'matched_candidates' => 8,
            'clusters_total' => 2,
            'ready_clusters' => [
                [
                    'cluster_id' => 1,
                    'user_id' => 'evt:' . $event->id . ':usr:1',
                    'face_count' => 5,
                    'media_count' => 5,
                    'event_media_ids' => [501],
                    'provider_record_ids' => [701, 702],
                    'face_ids' => ['face-701', 'face-702'],
                    'local_face_ids' => [901],
                ],
                [
                    'cluster_id' => 2,
                    'user_id' => 'evt:' . $event->id . ':usr:2',
                    'face_count' => 5,
                    'media_count' => 5,
                    'event_media_ids' => [502],
                    'provider_record_ids' => [703, 704],
                    'face_ids' => ['face-703', 'face-704'],
                    'local_face_ids' => [902],
                ],
            ],
            'pending_clusters' => [],
        ]);

    $probeBuilder = m::mock(AwsUserHighCardinalityProbeBuilder::class);
    $probeBuilder->shouldReceive('build')
        ->once()
        ->andReturn([
            [
                'cluster_id' => 1,
                'expected_user_id' => 'evt:' . $event->id . ':usr:1',
                'expected_event_media_ids' => [501],
                'expected_provider_record_ids' => [701, 702],
                'expected_face_ids' => ['face-701', 'face-702'],
                'local_face_id' => 901,
                'event_media_id' => 501,
                'source_ref' => 'local:testing/probe-a.jpg',
                'scale_factor' => 2.0,
                'probe_path' => $probePathA,
            ],
            [
                'cluster_id' => 2,
                'expected_user_id' => 'evt:' . $event->id . ':usr:2',
                'expected_event_media_ids' => [502],
                'expected_provider_record_ids' => [703, 704],
                'expected_face_ids' => ['face-703', 'face-704'],
                'local_face_id' => 902,
                'event_media_id' => 502,
                'source_ref' => 'local:testing/probe-b.jpg',
                'scale_factor' => 2.0,
                'probe_path' => $probePathB,
            ],
        ]);

    $searchBySelfie = m::mock(SearchFacesBySelfieAction::class);
    $call = 0;
    $searchBySelfie->shouldReceive('execute')
        ->twice()
        ->withArgs(function (Event $resolvedEvent, UploadedFile $selfie, string $requesterType): bool {
            return $resolvedEvent->exists
                && $selfie->isValid()
                && $requesterType === 'users_high_cardinality_validation';
        })
        ->andReturnUsing(function (Event $resolvedEvent) use (&$call) {
            $call++;
            $request = EventFaceSearchRequest::factory()->create([
                'event_id' => $resolvedEvent->id,
            ]);

            $expectedUserId = 'evt:' . $resolvedEvent->id . ':usr:' . $call;
            $duration = $call === 1 ? 410 : 560;
            $eventMediaId = $call === 1 ? 501 : 502;

            FaceSearchQuery::factory()->create([
                'event_id' => $resolvedEvent->id,
                'event_face_search_request_id' => $request->id,
                'provider_payload_json' => [
                    'fallback_triggered' => false,
                    'response_duration_ms' => $duration,
                    'provider_response' => [
                        'search_mode_requested' => 'users',
                        'search_mode_resolved' => 'users',
                        'search_mode_fallback_reason' => null,
                        'UserMatches' => [
                            [
                                'Similarity' => 99.4,
                                'User' => [
                                    'UserId' => $expectedUserId,
                                ],
                            ],
                        ],
                    ],
                ],
                'result_count' => 1,
            ]);

            return [
                'request' => $request,
                'results' => [
                    ['event_media_id' => $eventMediaId],
                ],
            ];
        });

    $action = new RunAwsUsersHighCardinalityValidationAction(
        healthCheck: $healthCheck,
        backend: $backend,
        searchBySelfie: $searchBySelfie,
        readiness: $readiness,
        probeBuilder: $probeBuilder,
    );

    $result = $action->execute(
        event: $event,
        sampleUsers: 2,
        minReadyUsers: 2,
        targetReadyUsers: 10,
        maxFallbackRate: 0.05,
        minUsersModeResolutionRate: 1.0,
        minTop1MatchRate: 1.0,
        minTopKMatchRate: 1.0,
        maxP95LatencyMs: 600,
        reconcileBefore: true,
        reconcileAfter: true,
    );

    expect($result['status'])->toBe('completed')
        ->and($result['metrics']['queries_attempted'])->toBe(2)
        ->and($result['metrics']['queries_completed'])->toBe(2)
        ->and($result['metrics']['ready_user_count'])->toBe(2)
        ->and($result['metrics']['target_ready_users_met'])->toBeFalse()
        ->and($result['metrics']['users_mode_resolution_rate'])->toBe(1.0)
        ->and($result['metrics']['fallback_rate'])->toBe(0.0)
        ->and($result['metrics']['top_1_match_rate'])->toBe(1.0)
        ->and($result['metrics']['top_k_match_rate'])->toBe(1.0)
        ->and($result['metrics']['p95_response_duration_ms'])->toBe(560)
        ->and($result['criteria_evaluation']['passed'])->toBeTrue()
        ->and($result['queries'][0]['evaluation']['top_1_match'])->toBeTrue()
        ->and($result['queries'][1]['evaluation']['top_k_match'])->toBeTrue();

    expect(File::exists($probePathA))->toBeFalse()
        ->and(File::exists($probePathB))->toBeFalse();
});
