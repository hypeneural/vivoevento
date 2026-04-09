<?php

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Models\EventMediaFace;
use App\Modules\FaceSearch\Services\AwsUserHighCardinalityProbeBuilder;
use App\Modules\FaceSearch\Services\AwsUserVectorReadinessService;
use App\Modules\FaceSearch\Services\FaceSearchMediaSourceLoader;
use App\Modules\FaceSearch\Services\SelfiePreflightService;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Mockery as m;

it('builds high-cardinality user probes from ready clusters with expected user metadata', function () {
    $event = Event::factory()->active()->create();

    $settings = \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'recognition_enabled' => true,
        'search_backend_key' => 'aws_rekognition',
        'aws_search_mode' => 'users',
    ]);

    $media = EventMedia::factory()->approved()->published()->create([
        'event_id' => $event->id,
        'media_type' => 'image',
    ]);

    $face = EventMediaFace::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'bbox_x' => 48,
        'bbox_y' => 52,
        'bbox_w' => 220,
        'bbox_h' => 220,
        'quality_score' => 0.97,
        'face_area_ratio' => 0.25,
        'is_primary_face_candidate' => true,
    ]);

    $sourceImage = UploadedFile::fake()->image('source.jpg', 640, 640)->size(200);
    $sourceBinary = (string) file_get_contents($sourceImage->getPathname());

    $sourceLoader = m::mock(FaceSearchMediaSourceLoader::class);
    $sourceLoader->shouldReceive('loadImageBinary')
        ->once()
        ->with(m::on(fn (EventMedia $resolvedMedia): bool => $resolvedMedia->is($media)))
        ->andReturn([
            'disk' => 'local',
            'path' => 'testing/source.jpg',
            'binary' => $sourceBinary,
            'source_ref' => 'local:testing/source.jpg',
        ]);

    $preflight = m::mock(SelfiePreflightService::class);
    $preflight->shouldReceive('validateForSearch')
        ->once()
        ->andReturn([
            'detected_faces_count' => 1,
        ]);

    $builder = new AwsUserHighCardinalityProbeBuilder(
        sourceLoader: $sourceLoader,
        preflight: $preflight,
        readiness: m::mock(AwsUserVectorReadinessService::class),
    );

    $probes = $builder->build($event, $settings, 1, [
        'ready_clusters' => [
            [
                'cluster_id' => 7,
                'user_id' => 'evt:' . $event->id . ':usr:seed',
                'face_count' => 5,
                'media_count' => 5,
                'event_media_ids' => [$media->id],
                'provider_record_ids' => [41, 42, 43],
                'face_ids' => ['face-a', 'face-b'],
                'local_face_ids' => [$face->id],
            ],
        ],
    ]);

    expect($probes)->toHaveCount(1)
        ->and($probes[0]['cluster_id'])->toBe(7)
        ->and($probes[0]['expected_user_id'])->toBe('evt:' . $event->id . ':usr:seed')
        ->and($probes[0]['event_media_id'])->toBe($media->id)
        ->and($probes[0]['local_face_id'])->toBe($face->id)
        ->and($probes[0]['expected_provider_record_ids'])->toBe([41, 42, 43])
        ->and(File::exists($probes[0]['probe_path']))->toBeTrue();

    File::delete($probes[0]['probe_path']);
});
