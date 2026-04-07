<?php

use App\Modules\ContentModeration\Models\EventMediaSafetyEvaluation;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\DTOs\FaceBoundingBoxData;
use App\Modules\FaceSearch\DTOs\FaceEmbeddingData;
use App\Modules\FaceSearch\Models\EventFaceSearchRequest;
use App\Modules\FaceSearch\Models\EventMediaFace;
use App\Modules\FaceSearch\Services\FaceDetectionProviderInterface;
use App\Modules\FaceSearch\Services\FaceEmbeddingProviderInterface;
use App\Modules\FaceSearch\Services\FaceVectorStoreInterface;
use App\Modules\MediaIntelligence\Models\EventMediaVlmEvaluation;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Models\EventMediaVariant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('deletes media artifacts projections and derived evaluations when media is removed', function () {
    Storage::fake('public');
    Storage::fake('ai-private');

    [, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    $originalPath = UploadedFile::fake()
        ->image('original.jpg', 1200, 900)
        ->store("events/{$event->id}/originals", 'public');

    $variantPath = UploadedFile::fake()
        ->image('gallery.jpg', 1200, 900)
        ->store("events/{$event->id}/variants/{$event->id}", 'public');

    $media = EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'original_disk' => 'public',
        'original_path' => $originalPath,
    ]);

    EventMediaVariant::query()->create([
        'event_media_id' => $media->id,
        'variant_key' => 'gallery',
        'disk' => 'public',
        'path' => $variantPath,
        'width' => 1200,
        'height' => 900,
        'size_bytes' => Storage::disk('public')->size($variantPath),
        'mime_type' => 'image/jpeg',
    ]);

    $cropPath = "events/{$event->id}/faces/{$media->id}/face-0.webp";
    Storage::disk('ai-private')->put($cropPath, 'face-crop');

    $face = \Database\Factories\EventMediaFaceFactory::new()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'crop_disk' => 'ai-private',
        'crop_path' => $cropPath,
        'embedding' => '[0.1,0.2,0.3]',
    ]);

    EventMediaSafetyEvaluation::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
    ]);

    EventMediaVlmEvaluation::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
    ]);

    $request = \Database\Factories\EventFaceSearchRequestFactory::new()->create([
        'event_id' => $event->id,
        'result_photo_ids_json' => [$media->id],
    ]);

    $tracker = new class
    {
        public array $deletedFaceIds = [];
    };

    app()->instance(FaceVectorStoreInterface::class, new class($tracker) implements FaceVectorStoreInterface
    {
        public function __construct(private readonly object $tracker) {}

        public function upsert(EventMediaFace $face, FaceEmbeddingData $embedding): EventMediaFace
        {
            return $face;
        }

        public function delete(EventMediaFace $face): void
        {
            $this->tracker->deletedFaceIds[] = $face->id;
        }

        public function search(int $eventId, array $queryEmbedding, int $topK, ?float $threshold = null, bool $searchableOnly = true, ?string $searchStrategy = null): array
        {
            return [];
        }
    });

    $response = $this->apiDelete("/media/{$media->id}");

    $response->assertStatus(204);

    app(\App\Modules\MediaProcessing\Jobs\CleanupDeletedMediaArtifactsJob::class, [
        'eventMediaId' => $media->id,
    ])->handle();

    $deletedMedia = EventMedia::withTrashed()->find($media->id);

    expect($deletedMedia)->not->toBeNull()
        ->and($deletedMedia?->trashed())->toBeTrue()
        ->and($deletedMedia?->publication_status?->value)->toBe('deleted')
        ->and($tracker->deletedFaceIds)->toContain($face->id);

    Storage::disk('public')->assertMissing($originalPath);
    Storage::disk('public')->assertMissing($variantPath);
    Storage::disk('ai-private')->assertMissing($cropPath);

    expect(EventMediaVariant::query()->where('event_media_id', $media->id)->count())->toBe(0)
        ->and(EventMediaFace::query()->where('event_media_id', $media->id)->count())->toBe(0)
        ->and(EventMediaSafetyEvaluation::query()->where('event_media_id', $media->id)->count())->toBe(0)
        ->and(EventMediaVlmEvaluation::query()->where('event_media_id', $media->id)->count())->toBe(0);

    expect($request->fresh()?->result_photo_ids_json)->toBeNull();
});

it('removes deleted media from public face search results after cleanup', function () {
    [, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'allow_public_selfie_search' => true,
    ]);

    EventModule::query()->create([
        'event_id' => $event->id,
        'module_key' => 'live',
        'is_enabled' => true,
    ]);

    $media = EventMedia::factory()->published()->create([
        'event_id' => $event->id,
    ]);

    \Database\Factories\EventMediaFaceFactory::new()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'embedding' => '[0.1,0.2,0.3]',
        'searchable' => true,
    ]);

    app()->instance(FaceDetectionProviderInterface::class, new class implements FaceDetectionProviderInterface
    {
        public function detect(EventMedia $media, \App\Modules\FaceSearch\Models\EventFaceSearchSetting $settings, string $binary): array
        {
            return [
                new DetectedFaceData(
                    boundingBox: new FaceBoundingBoxData(10, 10, 160, 160),
                    detectionConfidence: 0.99,
                    qualityScore: 0.95,
                    sharpnessScore: 0.90,
                    faceAreaRatio: 0.18,
                    isPrimaryCandidate: true,
                ),
            ];
        }
    });

    app()->instance(FaceEmbeddingProviderInterface::class, new class implements FaceEmbeddingProviderInterface
    {
        public function embed(EventMedia $media, \App\Modules\FaceSearch\Models\EventFaceSearchSetting $settings, string $cropBinary, DetectedFaceData $face): FaceEmbeddingData
        {
            return new FaceEmbeddingData(
                vector: [0.1, 0.2, 0.3],
                providerKey: 'noop',
                providerVersion: 'foundation-v1',
                modelKey: 'face-embedding-foundation-v1',
                modelSnapshot: 'face-embedding-foundation-v1',
                embeddingVersion: 'foundation-v1',
            );
        }
    });

    $beforeDelete = $this->withHeaders(['Accept' => 'application/json'])->post("/api/v1/public/events/{$event->slug}/face-search/search", [
        'consent_version' => 'consent-v1',
        'consent_accepted' => '1',
        'selfie' => UploadedFile::fake()->image('selfie.jpg', 400, 400),
    ]);

    $beforeDelete->assertOk()
        ->assertJsonCount(1, 'data.results');

    $this->apiDelete("/media/{$media->id}")->assertStatus(204);

    app(\App\Modules\MediaProcessing\Jobs\CleanupDeletedMediaArtifactsJob::class, [
        'eventMediaId' => $media->id,
    ])->handle();

    $afterDelete = $this->withHeaders(['Accept' => 'application/json'])->post("/api/v1/public/events/{$event->slug}/face-search/search", [
        'consent_version' => 'consent-v1',
        'consent_accepted' => '1',
        'selfie' => UploadedFile::fake()->image('selfie-2.jpg', 400, 400),
    ]);

    $afterDelete->assertOk()
        ->assertJsonCount(0, 'data.results');
});
