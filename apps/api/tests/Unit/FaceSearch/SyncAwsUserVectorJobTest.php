<?php

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Jobs\SyncAwsUserVectorJob;
use App\Modules\FaceSearch\Models\EventMediaFace;
use App\Modules\FaceSearch\Models\FaceSearchProviderRecord;
use App\Modules\FaceSearch\Services\AwsRekognitionFaceSearchBackend;
use App\Modules\FaceSearch\Services\AwsUserVectorReadinessService;
use App\Modules\MediaProcessing\Models\EventMedia;
use Mockery as m;

it('syncs ready aws user vectors for an event', function () {
    $event = Event::factory()->create();

    $settings = \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'recognition_enabled' => true,
        'search_backend_key' => 'aws_rekognition',
        'aws_collection_id' => 'eventovivo-face-search-event-' . $event->id,
    ]);

    $faceIds = [];

    foreach (range(1, 5) as $index) {
        $media = EventMedia::factory()->approved()->published()->create([
            'event_id' => $event->id,
        ]);

        EventMediaFace::factory()->create([
            'event_id' => $event->id,
            'event_media_id' => $media->id,
            'bbox_x' => 20,
            'bbox_y' => 20,
            'bbox_w' => 180,
            'bbox_h' => 180,
            'pose_yaw' => -10 + ($index * 5),
            'pose_pitch' => -2 + $index,
            'embedding' => sprintf('[0.93,%0.3f,0.08]', 0.12 + ($index * 0.004)),
        ]);

        $faceId = sprintf('20000000-0000-0000-0000-%012d', $index);
        $faceIds[] = $faceId;

        FaceSearchProviderRecord::factory()->create([
            'event_id' => $event->id,
            'event_media_id' => $media->id,
            'backend_key' => 'aws_rekognition',
            'provider_key' => 'aws_rekognition',
            'collection_id' => $settings->aws_collection_id,
            'face_id' => $faceId,
            'bbox_json' => [
                'x' => 20,
                'y' => 20,
                'width' => 180,
                'height' => 180,
            ],
            'pose_json' => [
                'yaw' => -10 + ($index * 5),
                'pitch' => -2 + $index,
                'roll' => 0,
            ],
            'quality_json' => [
                'composed_quality_score' => 0.92,
                'quality_tier' => 'search_priority',
                'face_area_ratio' => 0.18,
            ],
            'searchable' => true,
        ]);
    }

    $backend = m::mock(AwsRekognitionFaceSearchBackend::class);
    $backend->shouldReceive('syncUserVector')
        ->once()
        ->withArgs(function (Event $resolvedEvent, $resolvedSettings, string $userId, array $resolvedFaceIds) use ($event, $settings, $faceIds): bool {
            return $resolvedEvent->is($event)
                && $resolvedSettings->is($settings)
                && str_starts_with($userId, 'evt:' . $event->id . ':usr:')
                && $resolvedFaceIds === $faceIds;
        })
        ->andReturn([
            'user_id' => 'evt:' . $event->id . ':usr:pr:1',
            'requested_face_count' => 5,
            'associated_face_count' => 5,
            'unsuccessful_face_count' => 0,
            'user_status' => 'ACTIVE',
        ]);

    $job = new SyncAwsUserVectorJob($event->id);
    $job->handle($backend, app(AwsUserVectorReadinessService::class));
});

it('does not sync aws user vectors when the event is not using aws recognition', function () {
    $event = Event::factory()->create();

    \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'recognition_enabled' => false,
        'search_backend_key' => 'local_pgvector',
    ]);

    $backend = m::mock(AwsRekognitionFaceSearchBackend::class);
    $backend->shouldNotReceive('syncUserVector');

    $job = new SyncAwsUserVectorJob($event->id);
    $job->handle($backend, app(AwsUserVectorReadinessService::class));
});
