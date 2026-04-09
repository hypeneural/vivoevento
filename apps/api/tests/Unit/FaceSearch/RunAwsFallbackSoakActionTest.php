<?php

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Actions\RunAwsFallbackSoakAction;
use App\Modules\FaceSearch\Actions\RunEventFaceSearchHealthCheckAction;
use App\Modules\FaceSearch\Actions\SearchFacesBySelfieAction;
use App\Modules\FaceSearch\Models\EventFaceSearchRequest;
use App\Modules\FaceSearch\Models\FaceSearchQuery;
use App\Modules\FaceSearch\Services\AwsFallbackSoakProbeBuilder;
use App\Modules\FaceSearch\Services\AwsRekognitionFaceSearchBackend;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Mockery as m;

it('runs a short aws fallback soak and aggregates query, latency, fallback and drift metrics', function () {
    $event = Event::factory()->active()->create([
        'title' => 'FaceSearch soak action',
    ]);

    \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'recognition_enabled' => true,
        'search_backend_key' => 'aws_rekognition',
        'fallback_backend_key' => 'local_pgvector',
        'routing_policy' => 'aws_primary_local_fallback',
        'shadow_mode_percentage' => 0,
    ]);

    $probeDirectory = storage_path('app/testing/face-search-soak/action');
    File::deleteDirectory($probeDirectory);
    File::ensureDirectoryExists($probeDirectory);

    $fakeProbe = UploadedFile::fake()->image('probe.jpg', 320, 320)->size(180);
    $probePath = $probeDirectory . DIRECTORY_SEPARATOR . 'probe.jpg';
    copy($fakeProbe->getPathname(), $probePath);

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
            'collection_id' => 'eventovivo-face-search-event-soak',
            'remote_face_count' => 4,
            'local_face_count_before' => 4,
            'matched_records' => 4,
            'restored_records' => 0,
            'remote_only_records_created' => 0,
            'local_only_records_soft_deleted' => 0,
        ]);

    $probeBuilder = m::mock(AwsFallbackSoakProbeBuilder::class);
    $probeBuilder->shouldReceive('build')
        ->once()
        ->andReturn([
            [
                'event_media_id' => 501,
                'source_ref' => 'public:events/soak/media-501.jpg',
                'scale_factor' => 2.0,
                'probe_path' => $probePath,
            ],
        ]);

    $searchBySelfie = m::mock(SearchFacesBySelfieAction::class);
    $searchBySelfie->shouldReceive('execute')
        ->once()
        ->withArgs(function (Event $resolvedEvent, UploadedFile $selfie, string $requesterType): bool {
            return $resolvedEvent->exists
                && $selfie->isValid()
                && $requesterType === 'soak_command';
        })
        ->andReturnUsing(function (Event $resolvedEvent) {
            $request = EventFaceSearchRequest::factory()->create([
                'event_id' => $resolvedEvent->id,
            ]);

            FaceSearchQuery::factory()->create([
                'event_id' => $resolvedEvent->id,
                'event_face_search_request_id' => $request->id,
                'provider_payload_json' => [
                    'fallback_triggered' => false,
                    'response_duration_ms' => 812,
                ],
                'result_count' => 1,
            ]);

            return [
                'request' => $request,
                'results' => [
                    ['event_media_id' => 777],
                ],
            ];
        });

    $action = new RunAwsFallbackSoakAction(
        healthCheck: $healthCheck,
        backend: $backend,
        searchBySelfie: $searchBySelfie,
        probeBuilder: $probeBuilder,
    );

    $result = $action->execute(
        event: $event,
        queriesPerEvent: 1,
        reconcileBefore: true,
        reconcileAfter: true,
    );

    expect($result['status'])->toBe('completed')
        ->and($result['metrics']['queries_attempted'])->toBe(1)
        ->and($result['metrics']['queries_completed'])->toBe(1)
        ->and($result['metrics']['fallback_count'])->toBe(0)
        ->and($result['metrics']['avg_response_duration_ms'])->toBe(812.0)
        ->and($result['metrics']['drift_detected_before'])->toBeFalse()
        ->and($result['metrics']['drift_detected_after'])->toBeFalse()
        ->and($result['queries'][0]['query_audit']['response_duration_ms'])->toBe(812);

    expect(File::exists($probePath))->toBeFalse();
});

it('skips aws fallback soak when the event is not in aws primary local fallback mode', function () {
    $event = Event::factory()->active()->create([
        'title' => 'FaceSearch soak skip',
    ]);

    \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'recognition_enabled' => true,
        'search_backend_key' => 'aws_rekognition',
        'fallback_backend_key' => 'local_pgvector',
        'routing_policy' => 'aws_primary_local_shadow',
        'shadow_mode_percentage' => 100,
    ]);

    $action = new RunAwsFallbackSoakAction(
        healthCheck: m::mock(RunEventFaceSearchHealthCheckAction::class),
        backend: m::mock(AwsRekognitionFaceSearchBackend::class),
        searchBySelfie: m::mock(SearchFacesBySelfieAction::class),
        probeBuilder: m::mock(AwsFallbackSoakProbeBuilder::class),
    );

    $result = $action->execute($event, 1, true, true);

    expect($result['status'])->toBe('skipped')
        ->and($result['skipped_reason'])->toBe('event_not_in_aws_primary_local_fallback');
});
