<?php

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\DTOs\FaceBoundingBoxData;
use App\Modules\FaceSearch\Services\AwsImagePreprocessor;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\FaceSearch\Models\FaceSearchProviderRecord;
use App\Modules\FaceSearch\Services\FaceQualityGateService;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Models\EventMediaVariant;
use App\Modules\FaceSearch\Services\AwsRekognitionClientFactory;
use App\Modules\FaceSearch\Services\AwsRekognitionFaceSearchBackend;
use App\Modules\MediaProcessing\Services\ProviderCircuitBreaker;
use Aws\Exception\AwsException;
use Aws\Rekognition\RekognitionClient;
use Aws\Command;
use App\Shared\Exceptions\ProviderCircuitOpenException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery as m;

it('provisions and persists collection metadata for an aws event backend', function () {
    $event = Event::factory()->create([
        'slug' => 'casamento-joana-mario',
    ]);

    $settings = \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'recognition_enabled' => true,
        'search_backend_key' => 'aws_rekognition',
        'aws_region' => 'eu-central-1',
        'aws_collection_id' => null,
        'aws_collection_arn' => null,
        'aws_face_model_version' => null,
    ]);

    $client = m::mock(RekognitionClient::class);
    $factory = m::mock(AwsRekognitionClientFactory::class);

    $factory->shouldReceive('makeRekognitionClient')
        ->once()
        ->with('index', ['region' => 'eu-central-1'])
        ->andReturn($client);

    $expectedCollectionId = "eventovivo-face-search-event-{$event->id}";

    $client->shouldReceive('createCollection')
        ->once()
        ->with(['CollectionId' => $expectedCollectionId])
        ->andReturn([
            'StatusCode' => 200,
        ]);

    $client->shouldReceive('describeCollection')
        ->once()
        ->with(['CollectionId' => $expectedCollectionId])
        ->andReturn([
            'CollectionARN' => 'arn:aws:rekognition:eu-central-1:123456789012:collection/' . $expectedCollectionId,
            'FaceModelVersion' => '7.0',
            'FaceCount' => 0,
        ]);

    $backend = new AwsRekognitionFaceSearchBackend($factory);

    $result = $backend->ensureEventBackend($event, $settings);

    $settings->refresh();

    expect($result['backend_key'])->toBe('aws_rekognition')
        ->and($result['status'])->toBe('ready')
        ->and($settings->aws_collection_id)->toBe($expectedCollectionId)
        ->and($settings->aws_face_model_version)->toBe('7.0')
        ->and($settings->aws_collection_arn)->toContain($expectedCollectionId);
});

it('treats an existing collection as idempotent success and still refreshes metadata', function () {
    $event = Event::factory()->create();

    $settings = \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'recognition_enabled' => true,
        'search_backend_key' => 'aws_rekognition',
        'aws_region' => 'eu-central-1',
    ]);

    $client = m::mock(RekognitionClient::class);
    $factory = m::mock(AwsRekognitionClientFactory::class);

    $factory->shouldReceive('makeRekognitionClient')
        ->once()
        ->with('index', ['region' => 'eu-central-1'])
        ->andReturn($client);

    $expectedCollectionId = "eventovivo-face-search-event-{$event->id}";

    $client->shouldReceive('createCollection')
        ->once()
        ->andThrow(new AwsException(
            'already exists',
            new Command('CreateCollection'),
            ['code' => 'ResourceAlreadyExistsException'],
        ));

    $client->shouldReceive('describeCollection')
        ->once()
        ->with(['CollectionId' => $expectedCollectionId])
        ->andReturn([
            'CollectionARN' => 'arn:aws:rekognition:eu-central-1:123456789012:collection/' . $expectedCollectionId,
            'FaceModelVersion' => '7.0',
            'FaceCount' => 3,
        ]);

    $backend = new AwsRekognitionFaceSearchBackend($factory);

    $result = $backend->ensureEventBackend($event, $settings);

    expect($result['status'])->toBe('ready')
        ->and($result['collection_id'])->toBe($expectedCollectionId);
});

it('indexes aws face records and persists unindexed faces telemetry', function () {
    Storage::fake('public');

    $event = Event::factory()->create();

    $settings = \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'recognition_enabled' => true,
        'search_backend_key' => 'aws_rekognition',
        'aws_region' => 'eu-central-1',
        'aws_collection_id' => 'eventovivo-face-search-event-' . $event->id,
        'aws_index_quality_filter' => 'AUTO',
        'aws_detection_attributes_json' => ['DEFAULT', 'FACE_OCCLUDED'],
        'min_face_size_px' => 48,
        'min_quality_score' => 0.60,
    ]);

    $path = UploadedFile::fake()
        ->image('gallery.jpg', 1200, 900)
        ->store("events/{$event->id}/variants/301", 'public');

    $media = EventMedia::factory()->approved()->create([
        'event_id' => $event->id,
        'media_type' => 'image',
    ]);

    EventMediaVariant::query()->create([
        'event_media_id' => $media->id,
        'variant_key' => 'gallery',
        'disk' => 'public',
        'path' => $path,
        'width' => 1200,
        'height' => 900,
        'size_bytes' => Storage::disk('public')->size($path),
        'mime_type' => 'image/jpeg',
    ]);

    $client = m::mock(RekognitionClient::class);
    $factory = m::mock(AwsRekognitionClientFactory::class);

    $factory->shouldReceive('makeRekognitionClient')
        ->once()
        ->with('index', ['region' => 'eu-central-1'])
        ->andReturn($client);

    $client->shouldReceive('indexFaces')
        ->once()
        ->with(m::on(function (array $payload) use ($settings): bool {
            return $payload['CollectionId'] === $settings->aws_collection_id
                && $payload['QualityFilter'] === 'AUTO'
                && $payload['MaxFaces'] === 100
                && $payload['DetectionAttributes'] === ['DEFAULT', 'FACE_OCCLUDED']
                && is_array($payload['Image'] ?? null)
                && is_string($payload['Image']['Bytes'] ?? null)
                && $payload['Image']['Bytes'] !== '';
        }))
        ->andReturn([
            'FaceRecords' => [
                [
                    'Face' => [
                        'FaceId' => 'face-1',
                        'ImageId' => 'image-1',
                        'ExternalImageId' => 'evt:' . $event->id . ':media:' . $media->id,
                        'Confidence' => 99.1,
                    ],
                    'FaceDetail' => [
                        'BoundingBox' => [
                            'Left' => 0.10,
                            'Top' => 0.10,
                            'Width' => 0.20,
                            'Height' => 0.20,
                        ],
                        'Quality' => [
                            'Brightness' => 82,
                            'Sharpness' => 91,
                        ],
                        'Pose' => [
                            'Yaw' => 1.0,
                            'Pitch' => 0.0,
                            'Roll' => 0.0,
                        ],
                        'Landmarks' => [
                            [
                                'Type' => 'eyeLeft',
                                'X' => 0.15,
                                'Y' => 0.18,
                            ],
                        ],
                    ],
                ],
            ],
            'UnindexedFaces' => [
                [
                    'Reasons' => ['LOW_SHARPNESS'],
                    'FaceDetail' => [
                        'BoundingBox' => [
                            'Left' => 0.55,
                            'Top' => 0.20,
                            'Width' => 0.15,
                            'Height' => 0.15,
                        ],
                        'Quality' => [
                            'Brightness' => 40,
                            'Sharpness' => 20,
                        ],
                        'Pose' => [
                            'Yaw' => 9.0,
                            'Pitch' => 2.0,
                            'Roll' => 0.0,
                        ],
                    ],
                ],
            ],
        ]);

    $backend = new AwsRekognitionFaceSearchBackend(
        $factory,
        new AwsImagePreprocessor,
        new FaceQualityGateService,
    );

    $result = $backend->indexMedia($media, $settings);

    $records = FaceSearchProviderRecord::query()
        ->where('event_media_id', $media->id)
        ->orderBy('id')
        ->get();

    expect($result['status'])->toBe('indexed')
        ->and($result['faces_detected'])->toBe(2)
        ->and($result['faces_indexed'])->toBe(1)
        ->and($records)->toHaveCount(2)
        ->and($records[0]->face_id)->toBe('face-1')
        ->and($records[0]->searchable)->toBeTrue()
        ->and($records[1]->face_id)->toBeNull()
        ->and($records[1]->unindexed_reasons_json)->toBe(['LOW_SHARPNESS']);
});

it('deletes aws faces that fail the event searchable gate and stores them as non searchable records', function () {
    Storage::fake('public');

    $event = Event::factory()->create();

    $settings = \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'recognition_enabled' => true,
        'search_backend_key' => 'aws_rekognition',
        'aws_region' => 'eu-central-1',
        'aws_collection_id' => 'eventovivo-face-search-event-' . $event->id,
        'min_face_size_px' => 48,
        'min_quality_score' => 0.60,
    ]);

    $path = UploadedFile::fake()
        ->image('gallery.jpg', 1200, 900)
        ->store("events/{$event->id}/variants/302", 'public');

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'media_type' => 'image',
        'moderation_status' => ModerationStatus::Rejected->value,
    ]);

    EventMediaVariant::query()->create([
        'event_media_id' => $media->id,
        'variant_key' => 'gallery',
        'disk' => 'public',
        'path' => $path,
        'width' => 1200,
        'height' => 900,
        'size_bytes' => Storage::disk('public')->size($path),
        'mime_type' => 'image/jpeg',
    ]);

    $client = m::mock(RekognitionClient::class);
    $factory = m::mock(AwsRekognitionClientFactory::class);

    $factory->shouldReceive('makeRekognitionClient')
        ->once()
        ->with('index', ['region' => 'eu-central-1'])
        ->andReturn($client);

    $client->shouldReceive('indexFaces')
        ->once()
        ->andReturn([
            'FaceRecords' => [
                [
                    'Face' => [
                        'FaceId' => 'face-rejected',
                        'ImageId' => 'image-rejected',
                        'ExternalImageId' => 'evt:' . $event->id . ':media:' . $media->id,
                        'Confidence' => 98.0,
                    ],
                    'FaceDetail' => [
                        'BoundingBox' => [
                            'Left' => 0.10,
                            'Top' => 0.10,
                            'Width' => 0.25,
                            'Height' => 0.25,
                        ],
                        'Quality' => [
                            'Brightness' => 84,
                            'Sharpness' => 88,
                        ],
                        'Pose' => [
                            'Yaw' => 0.0,
                            'Pitch' => 0.0,
                            'Roll' => 0.0,
                        ],
                    ],
                ],
            ],
            'UnindexedFaces' => [],
        ]);

    $client->shouldReceive('deleteFaces')
        ->once()
        ->with([
            'CollectionId' => $settings->aws_collection_id,
            'FaceIds' => ['face-rejected'],
        ])
        ->andReturn([
            'DeletedFaces' => ['face-rejected'],
        ]);

    $backend = new AwsRekognitionFaceSearchBackend(
        $factory,
        new AwsImagePreprocessor,
        new FaceQualityGateService,
    );

    $result = $backend->indexMedia($media, $settings);

    $record = FaceSearchProviderRecord::query()
        ->where('event_media_id', $media->id)
        ->first();

    expect($result['status'])->toBe('skipped')
        ->and($result['faces_detected'])->toBe(1)
        ->and($result['faces_indexed'])->toBe(0)
        ->and($record)->not->toBeNull()
        ->and($record?->face_id)->toBe('face-rejected')
        ->and($record?->searchable)->toBeFalse();
});

it('searches faces by image and maps aws face ids back to searchable event media records', function () {
    Storage::fake('public');

    $event = Event::factory()->create();

    $settings = \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'recognition_enabled' => true,
        'search_backend_key' => 'aws_rekognition',
        'aws_region' => 'eu-central-1',
        'aws_collection_id' => 'eventovivo-face-search-event-' . $event->id,
        'aws_search_face_match_threshold' => 82,
        'aws_search_faces_quality_filter' => 'NONE',
    ]);

    $matchedMedia = EventMedia::factory()->approved()->published()->create([
        'event_id' => $event->id,
    ]);

    $searchableRecord = \Database\Factories\FaceSearchProviderRecordFactory::new()->create([
        'event_id' => $event->id,
        'event_media_id' => $matchedMedia->id,
        'provider_key' => 'aws_rekognition',
        'backend_key' => 'aws_rekognition',
        'collection_id' => $settings->aws_collection_id,
        'face_id' => 'face-1',
        'searchable' => true,
        'quality_json' => [
            'composed_quality_score' => 0.93,
            'quality_tier' => 'search_priority',
        ],
    ]);

    \Database\Factories\FaceSearchProviderRecordFactory::new()->create([
        'event_id' => $event->id,
        'event_media_id' => $matchedMedia->id,
        'provider_key' => 'aws_rekognition',
        'backend_key' => 'aws_rekognition',
        'collection_id' => $settings->aws_collection_id,
        'face_id' => 'face-hidden',
        'searchable' => false,
    ]);

    $probeMedia = EventMedia::factory()->create([
        'event_id' => $event->id,
    ]);

    $selfiePath = UploadedFile::fake()
        ->image('selfie.jpg', 1200, 900)
        ->store('tmp', 'public');
    $selfieBinary = Storage::disk('public')->get($selfiePath);

    $client = m::mock(RekognitionClient::class);
    $factory = m::mock(AwsRekognitionClientFactory::class);

    $factory->shouldReceive('makeRekognitionClient')
        ->once()
        ->with('query', ['region' => 'eu-central-1'])
        ->andReturn($client);

    $client->shouldReceive('searchFacesByImage')
        ->once()
        ->with(m::on(function (array $payload) use ($settings): bool {
            return $payload['CollectionId'] === $settings->aws_collection_id
                && $payload['FaceMatchThreshold'] === 82.0
                && $payload['MaxFaces'] === 12
                && $payload['QualityFilter'] === 'NONE'
                && is_string($payload['Image']['Bytes'] ?? null);
        }))
        ->andReturn([
            'FaceMatches' => [
                [
                    'Face' => [
                        'FaceId' => 'face-1',
                        'ImageId' => 'image-1',
                        'ExternalImageId' => 'evt:' . $event->id . ':media:' . $matchedMedia->id,
                    ],
                    'Similarity' => 98.5,
                ],
                [
                    'Face' => [
                        'FaceId' => 'face-hidden',
                        'ImageId' => 'image-hidden',
                    ],
                    'Similarity' => 96.0,
                ],
                [
                    'Face' => [
                        'FaceId' => 'face-missing',
                        'ImageId' => 'image-missing',
                    ],
                    'Similarity' => 95.0,
                ],
            ],
            'SearchedFaceBoundingBox' => [
                'Left' => 0.20,
                'Top' => 0.15,
                'Width' => 0.30,
                'Height' => 0.35,
            ],
            'SearchedFaceConfidence' => 99.1,
            'FaceModelVersion' => '7.0',
        ]);

    $backend = new AwsRekognitionFaceSearchBackend(
        $factory,
        new AwsImagePreprocessor,
        new FaceQualityGateService,
    );

    $result = $backend->searchBySelfie(
        event: $event,
        settings: $settings,
        probeMedia: $probeMedia,
        binary: $selfieBinary,
        face: new DetectedFaceData(
            boundingBox: new FaceBoundingBoxData(40, 50, 180, 180),
            detectionConfidence: 0.99,
            qualityScore: 0.92,
            isPrimaryCandidate: true,
        ),
        topK: 12,
    );

    expect($result['matches'])->toHaveCount(1)
        ->and($result['matches'][0]->eventMediaId)->toBe($matchedMedia->id)
        ->and($result['matches'][0]->faceId)->toBe($searchableRecord->id)
        ->and($result['matches'][0]->distance)->toBe(0.015)
        ->and($result['matches'][0]->qualityTier)->toBe('search_priority')
        ->and($result['provider_payload_json']['SearchedFaceConfidence'] ?? null)->toBe(99.1);
});

it('searches aws user vectors by selfie when the event opts into users mode', function () {
    Storage::fake('public');

    $event = Event::factory()->create();

    $settings = \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'recognition_enabled' => true,
        'search_backend_key' => 'aws_rekognition',
        'aws_region' => 'eu-central-1',
        'aws_collection_id' => 'eventovivo-face-search-event-' . $event->id,
        'aws_search_mode' => 'users',
        'aws_search_user_match_threshold' => 84,
        'aws_search_users_quality_filter' => 'AUTO',
    ]);

    $matchedMedia = EventMedia::factory()->approved()->published()->create([
        'event_id' => $event->id,
    ]);

    $searchableRecord = \Database\Factories\FaceSearchProviderRecordFactory::new()->create([
        'event_id' => $event->id,
        'event_media_id' => $matchedMedia->id,
        'provider_key' => 'aws_rekognition',
        'backend_key' => 'aws_rekognition',
        'collection_id' => $settings->aws_collection_id,
        'face_id' => '30000000-0000-0000-0000-000000000001',
        'user_id' => 'evt:' . $event->id . ':usr:pr:1',
        'searchable' => true,
        'quality_json' => [
            'composed_quality_score' => 0.95,
            'quality_tier' => 'search_priority',
            'face_area_ratio' => 0.20,
        ],
    ]);

    $probeMedia = EventMedia::factory()->create([
        'event_id' => $event->id,
    ]);

    $selfiePath = UploadedFile::fake()
        ->image('selfie.jpg', 1200, 900)
        ->store('tmp', 'public');
    $selfieBinary = Storage::disk('public')->get($selfiePath);

    $client = m::mock(RekognitionClient::class);
    $factory = m::mock(AwsRekognitionClientFactory::class);

    $factory->shouldReceive('makeRekognitionClient')
        ->once()
        ->with('query', ['region' => 'eu-central-1'])
        ->andReturn($client);

    $client->shouldReceive('searchUsersByImage')
        ->once()
        ->with(m::on(function (array $payload) use ($settings): bool {
            return $payload['CollectionId'] === $settings->aws_collection_id
                && $payload['UserMatchThreshold'] === 84.0
                && $payload['MaxUsers'] === 12
                && $payload['QualityFilter'] === 'AUTO'
                && is_string($payload['Image']['Bytes'] ?? null);
        }))
        ->andReturn([
            'UserMatches' => [
                [
                    'Similarity' => 97.4,
                    'User' => [
                        'UserId' => 'evt:' . $event->id . ':usr:pr:1',
                        'UserStatus' => 'ACTIVE',
                    ],
                ],
            ],
            'FaceModelVersion' => '7.0',
        ]);

    $backend = new AwsRekognitionFaceSearchBackend(
        $factory,
        new AwsImagePreprocessor,
        new FaceQualityGateService,
    );

    $result = $backend->searchBySelfie(
        event: $event,
        settings: $settings,
        probeMedia: $probeMedia,
        binary: $selfieBinary,
        face: new DetectedFaceData(
            boundingBox: new FaceBoundingBoxData(40, 50, 180, 180),
            detectionConfidence: 0.99,
            qualityScore: 0.92,
            isPrimaryCandidate: true,
        ),
        topK: 12,
    );

    expect($result['matches'])->toHaveCount(1)
        ->and($result['matches'][0]->eventMediaId)->toBe($matchedMedia->id)
        ->and($result['matches'][0]->faceId)->toBe($searchableRecord->id)
        ->and($result['matches'][0]->distance)->toBe(0.026)
        ->and(data_get($result['provider_payload_json'], 'search_mode_requested'))->toBe('users')
        ->and(data_get($result['provider_payload_json'], 'search_mode_resolved'))->toBe('users');
});

it('falls back to aws face search when the event requests users mode but no user vectors are ready', function () {
    Storage::fake('public');

    $event = Event::factory()->create();

    $settings = \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'recognition_enabled' => true,
        'search_backend_key' => 'aws_rekognition',
        'aws_region' => 'eu-central-1',
        'aws_collection_id' => 'eventovivo-face-search-event-' . $event->id,
        'aws_search_mode' => 'users',
        'aws_search_face_match_threshold' => 82,
    ]);

    $matchedMedia = EventMedia::factory()->approved()->published()->create([
        'event_id' => $event->id,
    ]);

    $searchableRecord = \Database\Factories\FaceSearchProviderRecordFactory::new()->create([
        'event_id' => $event->id,
        'event_media_id' => $matchedMedia->id,
        'provider_key' => 'aws_rekognition',
        'backend_key' => 'aws_rekognition',
        'collection_id' => $settings->aws_collection_id,
        'face_id' => '40000000-0000-0000-0000-000000000001',
        'user_id' => null,
        'searchable' => true,
        'quality_json' => [
            'composed_quality_score' => 0.91,
            'quality_tier' => 'search_priority',
        ],
    ]);

    $probeMedia = EventMedia::factory()->create([
        'event_id' => $event->id,
    ]);

    $selfiePath = UploadedFile::fake()
        ->image('selfie.jpg', 1200, 900)
        ->store('tmp', 'public');
    $selfieBinary = Storage::disk('public')->get($selfiePath);

    $client = m::mock(RekognitionClient::class);
    $factory = m::mock(AwsRekognitionClientFactory::class);

    $factory->shouldReceive('makeRekognitionClient')
        ->once()
        ->with('query', ['region' => 'eu-central-1'])
        ->andReturn($client);

    $client->shouldReceive('searchFacesByImage')
        ->once()
        ->andReturn([
            'FaceMatches' => [
                [
                    'Face' => [
                        'FaceId' => '40000000-0000-0000-0000-000000000001',
                    ],
                    'Similarity' => 96.0,
                ],
            ],
        ]);

    $backend = new AwsRekognitionFaceSearchBackend(
        $factory,
        new AwsImagePreprocessor,
        new FaceQualityGateService,
    );

    $result = $backend->searchBySelfie(
        event: $event,
        settings: $settings,
        probeMedia: $probeMedia,
        binary: $selfieBinary,
        face: new DetectedFaceData(
            boundingBox: new FaceBoundingBoxData(40, 50, 180, 180),
            detectionConfidence: 0.99,
            qualityScore: 0.92,
            isPrimaryCandidate: true,
        ),
        topK: 12,
    );

    expect($result['matches'])->toHaveCount(1)
        ->and($result['matches'][0]->faceId)->toBe($searchableRecord->id)
        ->and(data_get($result['provider_payload_json'], 'search_mode_requested'))->toBe('users')
        ->and(data_get($result['provider_payload_json'], 'search_mode_resolved'))->toBe('faces')
        ->and(data_get($result['provider_payload_json'], 'search_mode_fallback_reason'))->toBe('user_vector_not_ready');
});

it('creates and associates aws user vectors idempotently and records per-face sync status', function () {
    $event = Event::factory()->create();

    $settings = \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'recognition_enabled' => true,
        'search_backend_key' => 'aws_rekognition',
        'aws_region' => 'eu-central-1',
        'aws_collection_id' => 'eventovivo-face-search-event-' . $event->id,
        'aws_associate_user_match_threshold' => 77,
    ]);

    $associatedRecord = \Database\Factories\FaceSearchProviderRecordFactory::new()->create([
        'event_id' => $event->id,
        'backend_key' => 'aws_rekognition',
        'provider_key' => 'aws_rekognition',
        'collection_id' => $settings->aws_collection_id,
        'face_id' => '50000000-0000-0000-0000-000000000001',
        'user_id' => null,
    ]);

    $failedRecord = \Database\Factories\FaceSearchProviderRecordFactory::new()->create([
        'event_id' => $event->id,
        'backend_key' => 'aws_rekognition',
        'provider_key' => 'aws_rekognition',
        'collection_id' => $settings->aws_collection_id,
        'face_id' => '50000000-0000-0000-0000-000000000002',
        'user_id' => null,
    ]);

    $client = m::mock(RekognitionClient::class);
    $factory = m::mock(AwsRekognitionClientFactory::class);

    $factory->shouldReceive('makeRekognitionClient')
        ->once()
        ->with('index', ['region' => 'eu-central-1'])
        ->andReturn($client);

    $client->shouldReceive('createUser')
        ->once()
        ->with(m::on(function (array $payload) use ($settings, $event): bool {
            return $payload['CollectionId'] === $settings->aws_collection_id
                && $payload['UserId'] === 'evt:' . $event->id . ':usr:pr:10'
                && is_string($payload['ClientRequestToken'] ?? null)
                && $payload['ClientRequestToken'] !== '';
        }))
        ->andReturn([]);

    $client->shouldReceive('associateFaces')
        ->once()
        ->with(m::on(function (array $payload) use ($settings): bool {
            return $payload['CollectionId'] === $settings->aws_collection_id
                && $payload['UserMatchThreshold'] === 77.0
                && $payload['FaceIds'] === [
                    '50000000-0000-0000-0000-000000000001',
                    '50000000-0000-0000-0000-000000000002',
                ]
                && is_string($payload['ClientRequestToken'] ?? null)
                && $payload['ClientRequestToken'] !== '';
        }))
        ->andReturn([
            'AssociatedFaces' => [
                [
                    'FaceId' => '50000000-0000-0000-0000-000000000001',
                ],
            ],
            'UnsuccessfulFaceAssociations' => [
                [
                    'FaceId' => '50000000-0000-0000-0000-000000000002',
                    'Reasons' => ['ASSOCIATED_TO_A_DIFFERENT_IDENTITY'],
                    'UserId' => 'evt:other:usr:99',
                    'Confidence' => 63.5,
                ],
            ],
            'UserStatus' => 'ACTIVE',
        ]);

    $backend = new AwsRekognitionFaceSearchBackend($factory);

    $summary = $backend->syncUserVector(
        event: $event,
        settings: $settings,
        userId: 'evt:' . $event->id . ':usr:pr:10',
        faceIds: [
            '50000000-0000-0000-0000-000000000001',
            '50000000-0000-0000-0000-000000000002',
        ],
    );

    $associatedRecord->refresh();
    $failedRecord->refresh();

    expect($summary)->toMatchArray([
        'user_id' => 'evt:' . $event->id . ':usr:pr:10',
        'requested_face_count' => 2,
        'associated_face_count' => 1,
        'unsuccessful_face_count' => 1,
        'user_status' => 'ACTIVE',
    ])
        ->and($associatedRecord->user_id)->toBe('evt:' . $event->id . ':usr:pr:10')
        ->and(data_get($associatedRecord->provider_payload_json, 'aws_user_vector.status'))->toBe('synced')
        ->and(data_get($failedRecord->provider_payload_json, 'aws_user_vector.status'))->toBe('failed')
        ->and(data_get($failedRecord->provider_payload_json, 'aws_user_vector.association.reasons'))->toBe(['ASSOCIATED_TO_A_DIFFERENT_IDENTITY'])
        ->and($failedRecord->user_id)->toBeNull();
});

it('opens the aws circuit after repeated search failures and blocks the immediate retry', function () {
    config()->set('face_search.providers.aws_rekognition.circuit_breaker.failure_threshold', 1);
    config()->set('face_search.providers.aws_rekognition.circuit_breaker.open_seconds', 60);
    Storage::fake('public');

    $event = Event::factory()->create();

    $settings = \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'recognition_enabled' => true,
        'search_backend_key' => 'aws_rekognition',
        'aws_region' => 'eu-central-1',
        'aws_collection_id' => 'eventovivo-face-search-event-' . $event->id,
    ]);

    $probeMedia = EventMedia::factory()->create([
        'event_id' => $event->id,
    ]);

    $selfiePath = UploadedFile::fake()
        ->image('selfie.jpg', 1200, 900)
        ->store('tmp', 'public');
    $selfieBinary = Storage::disk('public')->get($selfiePath);

    $client = m::mock(RekognitionClient::class);
    $factory = m::mock(AwsRekognitionClientFactory::class);

    $factory->shouldReceive('makeRekognitionClient')
        ->once()
        ->with('query', ['region' => 'eu-central-1'])
        ->andReturn($client);

    $client->shouldReceive('searchFacesByImage')
        ->once()
        ->andThrow(new AwsException(
            'throttled',
            new Command('SearchFacesByImage'),
            ['code' => 'ThrottlingException'],
        ));

    $backend = new AwsRekognitionFaceSearchBackend(
        $factory,
        new AwsImagePreprocessor,
        new FaceQualityGateService,
        circuitBreaker: app(ProviderCircuitBreaker::class),
    );

    try {
        $backend->searchBySelfie(
            event: $event,
            settings: $settings,
            probeMedia: $probeMedia,
            binary: $selfieBinary,
            face: new DetectedFaceData(
                boundingBox: new FaceBoundingBoxData(40, 50, 180, 180),
                detectionConfidence: 0.99,
                qualityScore: 0.92,
                isPrimaryCandidate: true,
            ),
            topK: 12,
        );
    } catch (AwsException) {
        // First failure opens the circuit.
    }

    expect(fn () => $backend->searchBySelfie(
        event: $event,
        settings: $settings,
        probeMedia: $probeMedia,
        binary: $selfieBinary,
        face: new DetectedFaceData(
            boundingBox: new FaceBoundingBoxData(40, 50, 180, 180),
            detectionConfidence: 0.99,
            qualityScore: 0.92,
            isPrimaryCandidate: true,
        ),
        topK: 12,
    ))->toThrow(ProviderCircuitOpenException::class);
});

it('reconciles aws collection drift by restoring remote matches, creating remote-only records and soft deleting local-only records', function () {
    $event = Event::factory()->create();

    $settings = \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'recognition_enabled' => true,
        'search_backend_key' => 'aws_rekognition',
        'aws_region' => 'eu-central-1',
        'aws_collection_id' => 'eventovivo-face-search-event-' . $event->id,
        'aws_collection_arn' => null,
        'aws_face_model_version' => null,
    ]);

    $matchedMedia = EventMedia::factory()->approved()->published()->create([
        'event_id' => $event->id,
    ]);
    $remoteOnlyMedia = EventMedia::factory()->approved()->published()->create([
        'event_id' => $event->id,
    ]);

    $matchedRecord = \Database\Factories\FaceSearchProviderRecordFactory::new()->create([
        'event_id' => $event->id,
        'event_media_id' => $matchedMedia->id,
        'backend_key' => 'aws_rekognition',
        'provider_key' => 'aws_rekognition',
        'collection_id' => $settings->aws_collection_id,
        'face_id' => 'face-matched',
        'searchable' => true,
    ]);

    $localOnlyRecord = \Database\Factories\FaceSearchProviderRecordFactory::new()->create([
        'event_id' => $event->id,
        'event_media_id' => $matchedMedia->id,
        'backend_key' => 'aws_rekognition',
        'provider_key' => 'aws_rekognition',
        'collection_id' => $settings->aws_collection_id,
        'face_id' => 'face-local-only',
        'searchable' => true,
    ]);

    $restoredRecord = \Database\Factories\FaceSearchProviderRecordFactory::new()->create([
        'event_id' => $event->id,
        'event_media_id' => $matchedMedia->id,
        'backend_key' => 'aws_rekognition',
        'provider_key' => 'aws_rekognition',
        'collection_id' => $settings->aws_collection_id,
        'face_id' => 'face-restored',
        'searchable' => true,
    ]);
    $restoredRecord->delete();

    $client = m::mock(RekognitionClient::class);
    $factory = m::mock(AwsRekognitionClientFactory::class);

    $factory->shouldReceive('makeRekognitionClient')
        ->once()
        ->with('query', ['region' => 'eu-central-1'])
        ->andReturn($client);

    $client->shouldReceive('describeCollection')
        ->once()
        ->with(['CollectionId' => $settings->aws_collection_id])
        ->andReturn([
            'CollectionARN' => 'arn:aws:rekognition:eu-central-1:123456789012:collection/' . $settings->aws_collection_id,
            'FaceModelVersion' => '7.0',
            'FaceCount' => 3,
        ]);

    $client->shouldReceive('listFaces')
        ->once()
        ->with([
            'CollectionId' => $settings->aws_collection_id,
            'MaxResults' => 4096,
        ])
        ->andReturn([
            'Faces' => [
                [
                    'FaceId' => 'face-matched',
                    'ImageId' => 'image-matched',
                    'ExternalImageId' => 'evt:' . $event->id . ':media:' . $matchedMedia->id . ':rev:abc123',
                    'Confidence' => 98.4,
                    'BoundingBox' => [
                        'Left' => 0.12,
                        'Top' => 0.10,
                        'Width' => 0.25,
                        'Height' => 0.25,
                    ],
                ],
                [
                    'FaceId' => 'face-restored',
                    'ImageId' => 'image-restored',
                    'ExternalImageId' => 'evt:' . $event->id . ':media:' . $matchedMedia->id . ':rev:def456',
                    'Confidence' => 97.0,
                    'BoundingBox' => [
                        'Left' => 0.32,
                        'Top' => 0.20,
                        'Width' => 0.20,
                        'Height' => 0.20,
                    ],
                ],
                [
                    'FaceId' => 'face-remote-only',
                    'ImageId' => 'image-remote-only',
                    'ExternalImageId' => 'evt:' . $event->id . ':media:' . $remoteOnlyMedia->id . ':rev:ghi789',
                    'Confidence' => 95.1,
                    'BoundingBox' => [
                        'Left' => 0.55,
                        'Top' => 0.18,
                        'Width' => 0.16,
                        'Height' => 0.16,
                    ],
                ],
            ],
        ]);

    $backend = new AwsRekognitionFaceSearchBackend($factory);

    $summary = $backend->reconcileCollection($event, $settings);

    $settings->refresh();
    $matchedRecord->refresh();
    $restoredRecord = FaceSearchProviderRecord::query()
        ->where('face_id', 'face-restored')
        ->first();
    $remoteOnlyRecord = FaceSearchProviderRecord::query()
        ->where('face_id', 'face-remote-only')
        ->first();
    $localOnlyRecord = FaceSearchProviderRecord::withTrashed()
        ->where('id', $localOnlyRecord->id)
        ->first();

    expect($summary)->toMatchArray([
        'backend_key' => 'aws_rekognition',
        'collection_id' => $settings->aws_collection_id,
        'remote_face_count' => 3,
        'local_face_count_before' => 2,
        'matched_records' => 1,
        'restored_records' => 1,
        'remote_only_records_created' => 1,
        'local_only_records_soft_deleted' => 1,
    ])->and($settings->aws_face_model_version)->toBe('7.0')
        ->and($settings->aws_collection_arn)->toContain($settings->aws_collection_id)
        ->and($matchedRecord->image_id)->toBe('image-matched')
        ->and($matchedRecord->bbox_json)->toMatchArray([
            'left' => 0.12,
            'top' => 0.10,
            'width' => 0.25,
            'height' => 0.25,
        ])
        ->and($restoredRecord)->not->toBeNull()
        ->and($restoredRecord?->trashed())->toBeFalse()
        ->and($remoteOnlyRecord)->not->toBeNull()
        ->and($remoteOnlyRecord?->event_media_id)->toBe($remoteOnlyMedia->id)
        ->and($remoteOnlyRecord?->searchable)->toBeFalse()
        ->and($remoteOnlyRecord?->unindexed_reasons_json)->toBe(['remote_only_face'])
        ->and($localOnlyRecord)->not->toBeNull()
        ->and($localOnlyRecord?->trashed())->toBeTrue();
});

it('deletes the aws collection idempotently and soft deletes local provider records', function () {
    $event = Event::factory()->create();

    $settings = \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'recognition_enabled' => true,
        'search_backend_key' => 'aws_rekognition',
        'aws_region' => 'eu-central-1',
        'aws_collection_id' => 'eventovivo-face-search-event-' . $event->id,
        'aws_collection_arn' => 'arn:aws:rekognition:eu-central-1:123456789012:collection/eventovivo-face-search-event-' . $event->id,
        'aws_face_model_version' => '7.0',
    ]);

    $providerRecord = \Database\Factories\FaceSearchProviderRecordFactory::new()->create([
        'event_id' => $event->id,
        'backend_key' => 'aws_rekognition',
        'provider_key' => 'aws_rekognition',
        'collection_id' => $settings->aws_collection_id,
        'face_id' => 'face-delete-me',
    ]);

    $client = m::mock(RekognitionClient::class);
    $factory = m::mock(AwsRekognitionClientFactory::class);

    $factory->shouldReceive('makeRekognitionClient')
        ->once()
        ->with('query', ['region' => 'eu-central-1'])
        ->andReturn($client);

    $client->shouldReceive('deleteCollection')
        ->once()
        ->with(['CollectionId' => $settings->aws_collection_id])
        ->andReturn([
            'StatusCode' => 200,
        ]);

    $backend = new AwsRekognitionFaceSearchBackend($factory);

    $backend->deleteEventBackend($event, $settings);

    $settings->refresh();
    $providerRecord = \App\Modules\FaceSearch\Models\FaceSearchProviderRecord::withTrashed()->find($providerRecord->id);

    expect($settings->aws_collection_id)->toBeNull()
        ->and($settings->aws_collection_arn)->toBeNull()
        ->and($settings->aws_face_model_version)->toBeNull()
        ->and($providerRecord)->not->toBeNull()
        ->and($providerRecord?->trashed())->toBeTrue();
});
