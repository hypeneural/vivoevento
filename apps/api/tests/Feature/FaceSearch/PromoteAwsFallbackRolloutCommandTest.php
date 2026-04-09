<?php

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Actions\IndexMediaFacesAction;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\FaceSearch\Services\AwsRekognitionFaceSearchBackend;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Mockery as m;

it('promotes stable shadow events to aws primary local fallback and stores a rollback-ready report', function () {
    $reportDir = storage_path('app/testing/face-search-rollout/promote');
    File::deleteDirectory($reportDir);
    Bus::fake();

    $events = collect(range(1, 2))->map(function (int $slot) {
        $event = Event::factory()->active()->create([
            'title' => "FaceSearch rollout {$slot}",
        ]);

        \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
            'event_id' => $event->id,
            'recognition_enabled' => true,
            'search_backend_key' => 'aws_rekognition',
            'fallback_backend_key' => 'local_pgvector',
            'routing_policy' => 'aws_primary_local_shadow',
            'shadow_mode_percentage' => 100,
        ]);

        EventMedia::factory()->create([
            'event_id' => $event->id,
            'media_type' => 'image',
        ]);

        return $event;
    });

    $backend = m::mock(AwsRekognitionFaceSearchBackend::class);
    $backend->shouldReceive('key')->andReturn('aws_rekognition');
    $backend->shouldReceive('ensureEventBackend')
        ->twice()
        ->andReturn([
            'backend_key' => 'aws_rekognition',
            'status' => 'ready',
            'collection_id' => 'eventovivo-face-search-rollout',
        ]);
    $backend->shouldReceive('reconcileCollection')
        ->twice()
        ->andReturn([
            'backend_key' => 'aws_rekognition',
            'collection_id' => 'eventovivo-face-search-rollout',
            'remote_face_count' => 1,
            'local_face_count_before' => 1,
            'matched_records' => 1,
            'restored_records' => 0,
            'remote_only_records_created' => 0,
            'local_only_records_soft_deleted' => 0,
        ]);
    $backend->shouldReceive('healthCheck')
        ->times(4)
        ->andReturn([
            'backend_key' => 'aws_rekognition',
            'status' => 'healthy',
            'checks' => [
                'identity' => 'ok',
                'collection' => 'ok',
                'list_faces' => 'ok',
            ],
        ]);

    app()->instance(AwsRekognitionFaceSearchBackend::class, $backend);

    $indexAction = m::mock(IndexMediaFacesAction::class);
    $indexAction->shouldReceive('execute')
        ->twice()
        ->andReturn([
            'status' => 'indexed',
            'faces_detected' => 1,
            'faces_indexed' => 1,
        ]);

    app()->instance(IndexMediaFacesAction::class, $indexAction);

    $this->artisan('face-search:promote-aws-fallback', [
        'event_ids' => $events->pluck('id')->all(),
        '--sync-index' => true,
        '--sync-reconcile' => true,
        '--report-dir' => $reportDir,
    ])->assertSuccessful();

    foreach ($events as $event) {
        $this->assertDatabaseHas('event_face_search_settings', [
            'event_id' => $event->id,
            'routing_policy' => 'aws_primary_local_fallback',
            'shadow_mode_percentage' => 0,
            'fallback_backend_key' => 'local_pgvector',
        ]);
    }

    $reports = File::files($reportDir);

    expect($reports)->toHaveCount(1);

    $report = json_decode((string) File::get($reports[0]->getPathname()), true, 512, JSON_THROW_ON_ERROR);

    expect($report['summary']['promoted'])->toBe(2)
        ->and($report['summary']['failed'])->toBe(0)
        ->and($report['events'][0]['status'])->toBe('promoted')
        ->and($report['events'][0]['previous_settings']['routing_policy'])->toBe('aws_primary_local_shadow')
        ->and($report['events'][0]['current_settings']['routing_policy'])->toBe('aws_primary_local_fallback');
});

it('skips rollout promotion when the event is not healthy enough for fallback mode', function () {
    $reportDir = storage_path('app/testing/face-search-rollout/skip');
    File::deleteDirectory($reportDir);
    Bus::fake();

    $event = Event::factory()->active()->create([
        'title' => 'FaceSearch rollout unhealthy',
    ]);

    \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'recognition_enabled' => true,
        'search_backend_key' => 'aws_rekognition',
        'fallback_backend_key' => 'local_pgvector',
        'routing_policy' => 'aws_primary_local_shadow',
        'shadow_mode_percentage' => 100,
    ]);

    $backend = m::mock(AwsRekognitionFaceSearchBackend::class);
    $backend->shouldReceive('key')->andReturn('aws_rekognition');
    $backend->shouldReceive('ensureEventBackend')
        ->once()
        ->andReturn([
            'backend_key' => 'aws_rekognition',
            'status' => 'ready',
            'collection_id' => 'eventovivo-face-search-rollout-unhealthy',
        ]);
    $backend->shouldReceive('healthCheck')
        ->once()
        ->andReturn([
            'backend_key' => 'aws_rekognition',
            'status' => 'misconfigured',
            'checks' => [
                'identity' => 'ok',
                'collection' => 'failed',
                'list_faces' => 'skipped',
            ],
        ]);

    app()->instance(AwsRekognitionFaceSearchBackend::class, $backend);

    $this->artisan('face-search:promote-aws-fallback', [
        'event_ids' => [$event->id],
        '--report-dir' => $reportDir,
    ])->assertSuccessful();

    $this->assertDatabaseHas('event_face_search_settings', [
        'event_id' => $event->id,
        'routing_policy' => 'aws_primary_local_shadow',
        'shadow_mode_percentage' => 100,
    ]);

    $reports = File::files($reportDir);
    $report = json_decode((string) File::get($reports[0]->getPathname()), true, 512, JSON_THROW_ON_ERROR);

    expect($report['summary']['promoted'])->toBe(0)
        ->and($report['summary']['skipped'])->toBe(1)
        ->and($report['events'][0]['status'])->toBe('skipped')
        ->and($report['events'][0]['skipped_reason'])->toBe('pre_promotion_health_not_healthy');
});
