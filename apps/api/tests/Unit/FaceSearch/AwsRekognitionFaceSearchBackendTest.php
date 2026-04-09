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
use Aws\Exception\AwsException;
use Aws\Rekognition\RekognitionClient;
use Aws\Command;
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
