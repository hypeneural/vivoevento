<?php

use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\DTOs\FaceBoundingBoxData;
use App\Modules\FaceSearch\DTOs\FaceEmbeddingData;
use App\Modules\FaceSearch\Jobs\IndexMediaFacesJob;
use App\Modules\FaceSearch\Services\AwsRekognitionFaceSearchBackend;
use App\Modules\FaceSearch\Services\FaceDetectionProviderInterface;
use App\Modules\FaceSearch\Services\FaceEmbeddingProviderInterface;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Models\EventMediaVariant;
use App\Modules\MediaProcessing\Models\MediaProcessingRun;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery as m;

it('marks face indexing as skipped when the event has face search disabled', function () {
    Storage::fake('public');
    Storage::fake('ai-private');

    $event = \App\Modules\Events\Models\Event::factory()->active()->create();

    $path = UploadedFile::fake()
        ->image('gallery.jpg', 1200, 900)
        ->store("events/{$event->id}/variants/100", 'public');

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'moderation_status' => ModerationStatus::Approved->value,
        'face_index_status' => 'queued',
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

    app(IndexMediaFacesJob::class, ['eventMediaId' => $media->id])->handle();

    $media->refresh();

    expect($media->face_index_status)->toBe('skipped');

    $run = MediaProcessingRun::query()
        ->where('event_media_id', $media->id)
        ->where('stage_key', 'face_index')
        ->latest('id')
        ->first();

    expect($run?->decision_key)->toBe('skipped')
        ->and($run?->queue_name)->toBe('face-index')
        ->and($run?->result_json['skipped_reason'] ?? null)->toBe('face_search_disabled');
});

it('indexes valid faces and stores private crops when face search is enabled', function () {
    Storage::fake('public');
    Storage::fake('ai-private');

    $event = \App\Modules\Events\Models\Event::factory()->active()->create();
    \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
    ]);

    app()->instance(FaceDetectionProviderInterface::class, new class implements FaceDetectionProviderInterface
    {
        public function detect(EventMedia $media, \App\Modules\FaceSearch\Models\EventFaceSearchSetting $settings, string $binary): array
        {
            return [
                new DetectedFaceData(
                    boundingBox: new FaceBoundingBoxData(40, 50, 180, 180),
                    detectionConfidence: 0.98,
                    qualityScore: 0.91,
                    sharpnessScore: 0.84,
                    faceAreaRatio: 0.12,
                    isPrimaryCandidate: true,
                ),
                new DetectedFaceData(
                    boundingBox: new FaceBoundingBoxData(320, 120, 150, 150),
                    detectionConfidence: 0.94,
                    qualityScore: 0.82,
                    sharpnessScore: 0.79,
                    faceAreaRatio: 0.09,
                ),
            ];
        }
    });

    app()->instance(FaceEmbeddingProviderInterface::class, new class implements FaceEmbeddingProviderInterface
    {
        public function embed(EventMedia $media, \App\Modules\FaceSearch\Models\EventFaceSearchSetting $settings, string $cropBinary, DetectedFaceData $face): FaceEmbeddingData
        {
            return new FaceEmbeddingData(
                vector: [0.11, 0.22, 0.33],
                providerKey: 'noop',
                providerVersion: 'foundation-v1',
                modelKey: 'face-embedding-foundation-v1',
                modelSnapshot: 'face-embedding-foundation-v1',
                embeddingVersion: 'foundation-v1',
            );
        }
    });

    $path = UploadedFile::fake()
        ->image('gallery.jpg', 1200, 900)
        ->store("events/{$event->id}/variants/200", 'public');

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'moderation_status' => ModerationStatus::Approved->value,
        'face_index_status' => 'queued',
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

    app(IndexMediaFacesJob::class, ['eventMediaId' => $media->id])->handle();

    $media->refresh();

    expect($media->face_index_status)->toBe('indexed');

    $faces = \App\Modules\FaceSearch\Models\EventMediaFace::query()
        ->where('event_media_id', $media->id)
        ->orderBy('face_index')
        ->get();

    expect($faces)->toHaveCount(2)
        ->and($faces[0]->searchable)->toBeTrue()
        ->and($faces[0]->is_primary_face_candidate)->toBeTrue()
        ->and($faces[1]->is_primary_face_candidate)->toBeFalse();

    Storage::disk('ai-private')->assertExists("events/{$event->id}/faces/{$media->id}/face-0.webp");
    Storage::disk('ai-private')->assertExists("events/{$event->id}/faces/{$media->id}/face-1.webp");

    $run = MediaProcessingRun::query()
        ->where('event_media_id', $media->id)
        ->where('stage_key', 'face_index')
        ->latest('id')
        ->first();

    expect($run?->decision_key)->toBe('indexed')
        ->and($run?->result_json['faces_detected'] ?? null)->toBe(2)
        ->and($run?->result_json['faces_indexed'] ?? null)->toBe(2);
});

it('indexes faces using compreface detection and embedding providers without a second provider request', function () {
    Storage::fake('public');
    Storage::fake('ai-private');

    config()->set('face_search.embedding_dimension', 3);
    config()->set('face_search.providers.compreface', [
        'base_url' => 'http://compreface.test',
        'api_key' => 'test-api-key',
        'face_plugins' => 'calculator,landmarks',
        'det_prob_threshold' => '0.70',
        'status' => true,
        'timeout' => 9,
        'connect_timeout' => 3,
        'provider_version' => 'compreface-rest-v1',
        'model' => 'compreface-face-v1',
        'model_snapshot' => 'compreface-face-v1',
        'use_base64' => true,
    ]);

    Http::fake([
        'http://compreface.test/api/v1/detection/detect*' => Http::response([
            'result' => [
                [
                    'box' => [
                        'probability' => 0.98,
                        'x_min' => 40,
                        'y_min' => 50,
                        'x_max' => 220,
                        'y_max' => 230,
                    ],
                    'landmarks' => [
                        [100, 120],
                        [160, 122],
                    ],
                    'embedding' => [0.11, 0.22, 0.33],
                ],
            ],
            'plugins_versions' => [
                'detector' => 'facenet.FaceDetector',
                'calculator' => 'facenet.Calculator',
            ],
        ]),
    ]);

    $event = \App\Modules\Events\Models\Event::factory()->active()->create();
    \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'provider_key' => 'compreface',
        'min_face_size_px' => 96,
        'min_quality_score' => 0.60,
    ]);

    $path = UploadedFile::fake()
        ->image('gallery.jpg', 1200, 900)
        ->store("events/{$event->id}/variants/202", 'public');

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'moderation_status' => ModerationStatus::Approved->value,
        'face_index_status' => 'queued',
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

    app(IndexMediaFacesJob::class, ['eventMediaId' => $media->id])->handle();

    $media->refresh();

    expect($media->face_index_status)->toBe('indexed');

    $face = \App\Modules\FaceSearch\Models\EventMediaFace::query()
        ->where('event_media_id', $media->id)
        ->first();

    expect($face)->not->toBeNull()
        ->and($face?->detection_confidence)->toBe(0.98)
        ->and($face?->embedding_model_key)->toBe('compreface-face-v1');

    Http::assertSentCount(1);
});

it('marks indexed faces as not searchable when the media is rejected', function () {
    $media = EventMedia::factory()->create([
        'moderation_status' => ModerationStatus::Approved->value,
    ]);

    \Database\Factories\EventMediaFaceFactory::new()->create([
        'event_id' => $media->event_id,
        'event_media_id' => $media->id,
        'searchable' => true,
    ]);

    app(\App\Modules\MediaProcessing\Actions\RejectEventMediaAction::class)->execute($media);

    expect(\App\Modules\FaceSearch\Models\EventMediaFace::query()->where('event_media_id', $media->id)->value('searchable'))
        ->toBeFalse();
});

it('classifies indexed faces into quality tiers and skips rejected faces before embedding', function () {
    Storage::fake('public');
    Storage::fake('ai-private');

    $event = \App\Modules\Events\Models\Event::factory()->active()->create();
    \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'min_face_size_px' => 100,
        'min_quality_score' => 0.70,
    ]);

    $embedTracker = new class
    {
        public int $calls = 0;
    };

    app()->instance(FaceDetectionProviderInterface::class, new class implements FaceDetectionProviderInterface
    {
        public function detect(EventMedia $media, \App\Modules\FaceSearch\Models\EventFaceSearchSetting $settings, string $binary): array
        {
            return [
                new DetectedFaceData(
                    boundingBox: new FaceBoundingBoxData(10, 10, 90, 90),
                    qualityScore: 0.95,
                ),
                new DetectedFaceData(
                    boundingBox: new FaceBoundingBoxData(140, 40, 110, 110),
                    qualityScore: 0.74,
                ),
                new DetectedFaceData(
                    boundingBox: new FaceBoundingBoxData(320, 80, 180, 180),
                    qualityScore: 0.90,
                    isPrimaryCandidate: true,
                ),
            ];
        }
    });

    app()->instance(FaceEmbeddingProviderInterface::class, new class($embedTracker) implements FaceEmbeddingProviderInterface
    {
        public function __construct(private readonly object $tracker) {}

        public function embed(EventMedia $media, \App\Modules\FaceSearch\Models\EventFaceSearchSetting $settings, string $cropBinary, DetectedFaceData $face): FaceEmbeddingData
        {
            $this->tracker->calls++;

            return new FaceEmbeddingData(
                vector: [0.11, 0.22, 0.33],
                providerKey: 'noop',
                providerVersion: 'foundation-v1',
                modelKey: 'face-embedding-foundation-v1',
                modelSnapshot: 'face-embedding-foundation-v1',
                embeddingVersion: 'foundation-v1',
            );
        }
    });

    $path = UploadedFile::fake()
        ->image('gallery.jpg', 1200, 900)
        ->store("events/{$event->id}/variants/203", 'public');

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'moderation_status' => ModerationStatus::Approved->value,
        'face_index_status' => 'queued',
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

    app(IndexMediaFacesJob::class, ['eventMediaId' => $media->id])->handle();

    $faces = \App\Modules\FaceSearch\Models\EventMediaFace::query()
        ->where('event_media_id', $media->id)
        ->orderBy('face_index')
        ->get();

    expect($faces)->toHaveCount(2)
        ->and($embedTracker->calls)->toBe(2)
        ->and($faces[0]->quality_tier)->toBe('index_only')
        ->and($faces[0]->quality_rejection_reason)->toBe('borderline_face_size')
        ->and($faces[1]->quality_tier)->toBe('search_priority')
        ->and($faces[1]->quality_rejection_reason)->toBeNull();
});

it('re-enables searchable faces when the media is approved by override', function () {
    $media = EventMedia::factory()->create([
        'moderation_status' => ModerationStatus::Rejected->value,
    ]);

    \Database\Factories\EventMediaFaceFactory::new()->create([
        'event_id' => $media->event_id,
        'event_media_id' => $media->id,
        'searchable' => false,
    ]);

    app(\App\Modules\MediaProcessing\Actions\ApproveEventMediaAction::class)->execute($media);

    expect(\App\Modules\FaceSearch\Models\EventMediaFace::query()->where('event_media_id', $media->id)->value('searchable'))
        ->toBeTrue();
});

it('marks face indexing as failed when the provider throws', function () {
    Storage::fake('public');
    Storage::fake('ai-private');

    $event = \App\Modules\Events\Models\Event::factory()->active()->create();
    \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
    ]);

    app()->instance(FaceDetectionProviderInterface::class, new class implements FaceDetectionProviderInterface
    {
        public function detect(EventMedia $media, \App\Modules\FaceSearch\Models\EventFaceSearchSetting $settings, string $binary): array
        {
            throw new RuntimeException('Detection provider offline');
        }
    });

    $path = UploadedFile::fake()
        ->image('gallery.jpg', 1200, 900)
        ->store("events/{$event->id}/variants/201", 'public');

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'moderation_status' => ModerationStatus::Approved->value,
        'face_index_status' => 'queued',
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

    expect(fn () => app(IndexMediaFacesJob::class, ['eventMediaId' => $media->id])->handle())
        ->toThrow(RuntimeException::class, 'Detection provider offline');

    $media->refresh();

    expect($media->face_index_status)->toBe('failed')
        ->and($media->last_pipeline_error_code)->toBe('face_index_failed');
});

it('routes face indexing through aws rekognition when the event backend is configured for aws', function () {
    Storage::fake('public');
    Storage::fake('ai-private');

    $event = \App\Modules\Events\Models\Event::factory()->active()->create();
    \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'recognition_enabled' => true,
        'search_backend_key' => 'aws_rekognition',
        'aws_collection_id' => 'eventovivo-face-search-event-' . $event->id,
    ]);

    $backend = m::mock(AwsRekognitionFaceSearchBackend::class);
    $backend->shouldReceive('key')
        ->andReturn('aws_rekognition');
    $backend->shouldReceive('indexMedia')
        ->once()
        ->andReturn([
            'status' => 'indexed',
            'source_ref' => 'public:events/' . $event->id . '/variants/900/gallery.jpg',
            'faces_detected' => 2,
            'faces_indexed' => 2,
            'skipped_reason' => null,
        ]);

    app()->instance(AwsRekognitionFaceSearchBackend::class, $backend);
    app()->instance(FaceDetectionProviderInterface::class, new class implements FaceDetectionProviderInterface
    {
        public function detect(EventMedia $media, \App\Modules\FaceSearch\Models\EventFaceSearchSetting $settings, string $binary): array
        {
            throw new RuntimeException('Local detector should not be used when AWS indexing is active.');
        }
    });

    $path = UploadedFile::fake()
        ->image('gallery.jpg', 1200, 900)
        ->store("events/{$event->id}/variants/900", 'public');

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'moderation_status' => ModerationStatus::Approved->value,
        'face_index_status' => 'queued',
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

    app(IndexMediaFacesJob::class, ['eventMediaId' => $media->id])->handle();

    $media->refresh();

    expect($media->face_index_status)->toBe('indexed');

    $run = MediaProcessingRun::query()
        ->where('event_media_id', $media->id)
        ->where('stage_key', 'face_index')
        ->latest('id')
        ->first();

    expect($run?->result_json['faces_detected'] ?? null)->toBe(2)
        ->and($run?->result_json['faces_indexed'] ?? null)->toBe(2);
});
