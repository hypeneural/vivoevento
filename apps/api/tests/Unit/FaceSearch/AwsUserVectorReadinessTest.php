<?php

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\FaceSearch\Models\EventMediaFace;
use App\Modules\FaceSearch\Models\FaceSearchProviderRecord;
use App\Modules\FaceSearch\Services\AwsUserVectorReadinessService;
use App\Modules\MediaProcessing\Models\EventMedia;

it('marks an aws user vector cluster as ready when enough good faces and pose variation exist', function () {
    $event = Event::factory()->create();

    $settings = \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'recognition_enabled' => true,
        'search_backend_key' => 'aws_rekognition',
        'aws_collection_id' => 'eventovivo-face-search-event-' . $event->id,
        'aws_index_profile_key' => 'default',
    ]);

    $yaws = [-12.0, -6.0, 0.0, 6.0, 12.0];
    $pitches = [-3.0, -1.0, 0.0, 1.5, 3.5];

    foreach ($yaws as $index => $yaw) {
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
            'pose_yaw' => $yaw,
            'pose_pitch' => $pitches[$index],
            'embedding' => sprintf('[0.90,%0.3f,0.10]', 0.10 + ($index * 0.005)),
        ]);

        FaceSearchProviderRecord::factory()->create([
            'event_id' => $event->id,
            'event_media_id' => $media->id,
            'backend_key' => 'aws_rekognition',
            'provider_key' => 'aws_rekognition',
            'collection_id' => $settings->aws_collection_id,
            'face_id' => sprintf('00000000-0000-0000-0000-%012d', $index + 1),
            'bbox_json' => [
                'x' => 20,
                'y' => 20,
                'width' => 180,
                'height' => 180,
            ],
            'pose_json' => [
                'yaw' => $yaw,
                'pitch' => $pitches[$index],
                'roll' => 0,
            ],
            'quality_json' => [
                'composed_quality_score' => 0.90 - ($index * 0.01),
                'quality_tier' => 'search_priority',
                'face_area_ratio' => 0.18,
            ],
            'searchable' => true,
            'user_id' => $index === 0 ? 'evt:' . $event->id . ':usr:seed' : null,
        ]);
    }

    $summary = app(AwsUserVectorReadinessService::class)->evaluate($event, $settings);

    expect($summary['matched_candidates'])->toBe(5)
        ->and($summary['clusters_total'])->toBe(1)
        ->and($summary['ready_clusters'])->toHaveCount(1)
        ->and($summary['pending_clusters'])->toHaveCount(0)
        ->and($summary['ready_clusters'][0]['user_id'])->toBe('evt:' . $event->id . ':usr:seed')
        ->and($summary['ready_clusters'][0]['local_face_ids'])->toHaveCount(5)
        ->and($summary['ready_clusters'][0]['face_count'])->toBe(5)
        ->and($summary['ready_clusters'][0]['yaw_spread'])->toBeGreaterThan(20.0)
        ->and($summary['ready_clusters'][0]['pitch_spread'])->toBeGreaterThan(6.0);
});

it('keeps aws user vector clusters pending when they do not meet the readiness gate', function () {
    $event = Event::factory()->create();

    $settings = \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'recognition_enabled' => true,
        'search_backend_key' => 'aws_rekognition',
        'aws_collection_id' => 'eventovivo-face-search-event-' . $event->id,
        'aws_index_profile_key' => 'default',
    ]);

    foreach (range(1, 4) as $index) {
        $media = EventMedia::factory()->approved()->published()->create([
            'event_id' => $event->id,
        ]);

        EventMediaFace::factory()->create([
            'event_id' => $event->id,
            'event_media_id' => $media->id,
            'bbox_x' => 24,
            'bbox_y' => 24,
            'bbox_w' => 160,
            'bbox_h' => 160,
            'pose_yaw' => 1.0,
            'pose_pitch' => 0.5,
            'embedding' => sprintf('[0.87,%0.3f,0.12]', 0.11 + ($index * 0.004)),
        ]);

        FaceSearchProviderRecord::factory()->create([
            'event_id' => $event->id,
            'event_media_id' => $media->id,
            'backend_key' => 'aws_rekognition',
            'provider_key' => 'aws_rekognition',
            'collection_id' => $settings->aws_collection_id,
            'face_id' => sprintf('10000000-0000-0000-0000-%012d', $index),
            'bbox_json' => [
                'x' => 24,
                'y' => 24,
                'width' => 160,
                'height' => 160,
            ],
            'pose_json' => [
                'yaw' => 1.0,
                'pitch' => 0.5,
                'roll' => 0,
            ],
            'quality_json' => [
                'composed_quality_score' => 0.88,
                'quality_tier' => 'search_priority',
                'face_area_ratio' => 0.15,
            ],
            'searchable' => true,
        ]);
    }

    $summary = app(AwsUserVectorReadinessService::class)->evaluate($event, $settings);

    expect($summary['ready_clusters'])->toHaveCount(0)
        ->and($summary['pending_clusters'])->toHaveCount(1)
        ->and($summary['pending_clusters'][0]['reason_codes'])->toContain('insufficient_faces')
        ->and($summary['pending_clusters'][0]['reason_codes'])->toContain('insufficient_yaw_variation')
        ->and($summary['pending_clusters'][0]['reason_codes'])->toContain('insufficient_pitch_variation');
});

it('matches crop indexed aws provider records back to local faces using the source bbox metadata', function () {
    $event = Event::factory()->create();

    $settings = \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'recognition_enabled' => true,
        'search_backend_key' => 'aws_rekognition',
        'aws_collection_id' => 'eventovivo-face-search-event-' . $event->id,
        'aws_index_profile_key' => 'default',
    ]);

    $yaws = [-10.0, -5.0, 0.0, 5.0, 11.0];
    $pitches = [-2.5, -1.0, 0.0, 1.0, 2.2];

    foreach ($yaws as $index => $yaw) {
        $media = EventMedia::factory()->approved()->published()->create([
            'event_id' => $event->id,
        ]);

        EventMediaFace::factory()->create([
            'event_id' => $event->id,
            'event_media_id' => $media->id,
            'bbox_x' => 24,
            'bbox_y' => 24,
            'bbox_w' => 180,
            'bbox_h' => 180,
            'pose_yaw' => $yaw,
            'pose_pitch' => $pitches[$index],
            'embedding' => sprintf('[0.91,%0.3f,0.08]', 0.12 + ($index * 0.004)),
        ]);

        FaceSearchProviderRecord::factory()->create([
            'event_id' => $event->id,
            'event_media_id' => $media->id,
            'backend_key' => 'aws_rekognition',
            'provider_key' => 'aws_rekognition',
            'collection_id' => $settings->aws_collection_id,
            'face_id' => sprintf('20000000-0000-0000-0000-%012d', $index + 1),
            'bbox_json' => [
                'x' => 0,
                'y' => 0,
                'width' => 120,
                'height' => 120,
            ],
            'pose_json' => [
                'yaw' => $yaw,
                'pitch' => $pitches[$index],
                'roll' => 0,
            ],
            'quality_json' => [
                'composed_quality_score' => 0.72,
                'quality_tier' => 'index_only',
                'face_area_ratio' => 0.15,
                'source_kind' => 'face_crop',
            ],
            'provider_payload_json' => [
                'index_input' => [
                    'source_kind' => 'face_crop',
                    'source_bbox' => [
                        'x' => 24,
                        'y' => 24,
                        'width' => 180,
                        'height' => 180,
                    ],
                ],
            ],
            'searchable' => true,
            'user_id' => $index === 0 ? 'evt:' . $event->id . ':usr:crop-seed' : null,
        ]);
    }

    $summary = app(AwsUserVectorReadinessService::class)->evaluate($event, $settings);

    expect($summary['matched_candidates'])->toBe(5)
        ->and($summary['clusters_total'])->toBe(1)
        ->and($summary['ready_clusters'])->toHaveCount(1)
        ->and($summary['ready_clusters'][0]['user_id'])->toBe('evt:' . $event->id . ':usr:crop-seed');
});

it('allows a 4-face organic cluster to become ready under the social gallery profile', function () {
    $event = Event::factory()->create();

    $settings = \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'recognition_enabled' => true,
        'search_backend_key' => 'aws_rekognition',
        'aws_collection_id' => 'eventovivo-face-search-event-' . $event->id,
        'aws_index_profile_key' => 'social_gallery_event',
    ]);

    $yaws = [-14.0, -4.0, 7.0, 14.0];
    $pitches = [-3.0, -0.5, 1.5, 4.5];

    foreach ($yaws as $index => $yaw) {
        $media = EventMedia::factory()->approved()->published()->create([
            'event_id' => $event->id,
        ]);

        EventMediaFace::factory()->create([
            'event_id' => $event->id,
            'event_media_id' => $media->id,
            'bbox_x' => 30,
            'bbox_y' => 30,
            'bbox_w' => 190,
            'bbox_h' => 190,
            'pose_yaw' => $yaw,
            'pose_pitch' => $pitches[$index],
            'embedding' => sprintf('[0.88,%0.3f,0.09]', 0.14 + ($index * 0.006)),
        ]);

        FaceSearchProviderRecord::factory()->create([
            'event_id' => $event->id,
            'event_media_id' => $media->id,
            'backend_key' => 'aws_rekognition',
            'provider_key' => 'aws_rekognition',
            'collection_id' => $settings->aws_collection_id,
            'face_id' => sprintf('30000000-0000-0000-0000-%012d', $index + 1),
            'bbox_json' => [
                'x' => 30,
                'y' => 30,
                'width' => 190,
                'height' => 190,
            ],
            'pose_json' => [
                'yaw' => $yaw,
                'pitch' => $pitches[$index],
                'roll' => 0,
            ],
            'quality_json' => [
                'composed_quality_score' => 0.86 - ($index * 0.01),
                'quality_tier' => 'search_priority',
                'face_area_ratio' => 0.17,
            ],
            'searchable' => true,
        ]);
    }

    $summary = app(AwsUserVectorReadinessService::class)->evaluate($event, $settings);

    expect($summary['min_faces_per_user'])->toBe(4)
        ->and($summary['ready_clusters'])->toHaveCount(1)
        ->and($summary['pending_clusters'])->toHaveCount(0)
        ->and($summary['ready_clusters'][0]['face_count'])->toBe(4)
        ->and($summary['ready_clusters'][0]['yaw_spread'])->toBeGreaterThan(20.0)
        ->and($summary['ready_clusters'][0]['pitch_spread'])->toBeGreaterThan(6.0);
});
