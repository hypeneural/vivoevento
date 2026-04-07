<?php

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\DTOs\FaceBoundingBoxData;
use App\Modules\FaceSearch\DTOs\FaceEmbeddingData;
use App\Modules\FaceSearch\Models\EventFaceSearchRequest;
use App\Modules\FaceSearch\Models\EventMediaFace;
use App\Modules\FaceSearch\Services\CompreFaceEmbeddingProvider;
use App\Modules\FaceSearch\Services\FaceDetectionProviderInterface;
use App\Modules\FaceSearch\Services\FaceEmbeddingProviderInterface;
use App\Modules\FaceSearch\Services\FaceVectorStoreInterface;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

it('returns only matches from the requested event in internal selfie search', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);
    $otherEvent = Event::factory()->active()->create();

    \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'top_k' => 10,
        'search_threshold' => 0.4,
    ]);
    \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $otherEvent->id,
        'top_k' => 10,
        'search_threshold' => 0.4,
    ]);

    bindSingleFaceSearchProviders([0.10, 0.20, 0.30]);

    $eventMedia = EventMedia::factory()->approved()->create([
        'event_id' => $event->id,
        'publication_status' => PublicationStatus::Draft->value,
    ]);
    $otherMedia = EventMedia::factory()->approved()->published()->create([
        'event_id' => $otherEvent->id,
    ]);

    seedFaceEmbedding($event->id, $eventMedia->id, [0.10, 0.20, 0.30]);
    seedFaceEmbedding($otherEvent->id, $otherMedia->id, [0.10, 0.20, 0.30]);

    $response = $this->withHeaders(['Accept' => 'application/json'])->post(
        "/api/v1/events/{$event->id}/face-search/search",
        [
            'selfie' => UploadedFile::fake()->image('selfie.jpg'),
            'include_pending' => true,
        ],
    );

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.total_results', 1);
    $response->assertJsonPath('data.results.0.event_media_id', $eventMedia->id);
    $response->assertJsonPath('data.results.0.media.id', $eventMedia->id);

    expect(EventFaceSearchRequest::query()->where('event_id', $event->id)->count())->toBe(1);
});

it('uses compreface calculator embedding to find a selfie match', function () {
    [$user, $organization] = $this->actingAsOwner();

    config()->set('face_search.embedding_dimension', 3);
    config()->set('face_search.providers.compreface', [
        'provider_version' => 'compreface-rest-v1',
        'model' => 'compreface-face-v1',
        'model_snapshot' => 'compreface-face-v1',
    ]);

    Http::fake();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'provider_key' => 'compreface',
        'top_k' => 10,
        'search_threshold' => 0.4,
    ]);

    app()->instance(FaceDetectionProviderInterface::class, new class implements FaceDetectionProviderInterface
    {
        public function detect(\App\Modules\MediaProcessing\Models\EventMedia $media, \App\Modules\FaceSearch\Models\EventFaceSearchSetting $settings, string $binary): array
        {
            return [
                new DetectedFaceData(
                    boundingBox: new FaceBoundingBoxData(24, 24, 180, 180),
                    detectionConfidence: 0.99,
                    qualityScore: 0.92,
                    isPrimaryCandidate: true,
                    providerEmbedding: [0.10, 0.20, 0.30],
                    providerPayload: [
                        'provider' => 'compreface',
                    ],
                ),
            ];
        }
    });

    app()->instance(FaceEmbeddingProviderInterface::class, app(CompreFaceEmbeddingProvider::class));

    $eventMedia = EventMedia::factory()->approved()->published()->create([
        'event_id' => $event->id,
    ]);
    seedFaceEmbedding($event->id, $eventMedia->id, [0.10, 0.20, 0.30]);

    $response = $this->withHeaders(['Accept' => 'application/json'])->post(
        "/api/v1/events/{$event->id}/face-search/search",
        [
            'selfie' => UploadedFile::fake()->image('selfie.jpg'),
            'include_pending' => true,
        ],
    );

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.total_results', 1);
    $response->assertJsonPath('data.results.0.event_media_id', $eventMedia->id);

    Http::assertSentCount(0);
});

it('passes the event search strategy to the vector store', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'search_strategy' => 'ann',
        'top_k' => 10,
        'search_threshold' => 0.4,
    ]);

    bindSingleFaceSearchProviders([0.10, 0.20, 0.30]);

    $store = new class implements FaceVectorStoreInterface
    {
        public ?string $searchStrategy = null;

        public function upsert(EventMediaFace $face, FaceEmbeddingData $embedding): EventMediaFace
        {
            return $face;
        }

        public function delete(EventMediaFace $face): void {}

        public function search(
            int $eventId,
            array $queryEmbedding,
            int $topK,
            ?float $threshold = null,
            bool $searchableOnly = true,
            ?string $searchStrategy = null,
        ): array {
            $this->searchStrategy = $searchStrategy;

            return [];
        }
    };

    app()->instance(FaceVectorStoreInterface::class, $store);

    $response = $this->withHeaders(['Accept' => 'application/json'])->post(
        "/api/v1/events/{$event->id}/face-search/search",
        [
            'selfie' => UploadedFile::fake()->image('selfie.jpg'),
            'include_pending' => true,
        ],
    );

    $this->assertApiSuccess($response);

    expect($store->searchStrategy)->toBe('ann');
});

it('returns a clear validation error when the selfie has no valid face', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
    ]);

    app()->instance(FaceDetectionProviderInterface::class, new class implements FaceDetectionProviderInterface
    {
        public function detect(\App\Modules\MediaProcessing\Models\EventMedia $media, \App\Modules\FaceSearch\Models\EventFaceSearchSetting $settings, string $binary): array
        {
            return [];
        }
    });

    $response = $this->withHeaders(['Accept' => 'application/json'])->post(
        "/api/v1/events/{$event->id}/face-search/search",
        [
            'selfie' => UploadedFile::fake()->image('sem-rosto.jpg'),
        ],
    );

    $this->assertApiValidationError($response, ['selfie']);

    $request = EventFaceSearchRequest::query()->where('event_id', $event->id)->latest('id')->first();

    expect($request?->status)->toBe('failed')
        ->and($request?->faces_detected)->toBe(0);
});

it('stores reject tier and reason when the selfie fails the quality gate', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'min_face_size_px' => 100,
        'min_quality_score' => 0.70,
    ]);

    app()->instance(FaceDetectionProviderInterface::class, new class implements FaceDetectionProviderInterface
    {
        public function detect(\App\Modules\MediaProcessing\Models\EventMedia $media, \App\Modules\FaceSearch\Models\EventFaceSearchSetting $settings, string $binary): array
        {
            return [
                new DetectedFaceData(
                    boundingBox: new FaceBoundingBoxData(10, 10, 90, 90),
                    qualityScore: 0.96,
                ),
            ];
        }
    });

    $response = $this->withHeaders(['Accept' => 'application/json'])->post(
        "/api/v1/events/{$event->id}/face-search/search",
        [
            'selfie' => UploadedFile::fake()->image('selfie-ruim.jpg'),
        ],
    );

    $this->assertApiValidationError($response, ['selfie']);

    $request = EventFaceSearchRequest::query()->where('event_id', $event->id)->latest('id')->first();

    expect($request?->status)->toBe('failed')
        ->and($request?->query_face_quality_tier)->toBe('reject')
        ->and($request?->query_face_rejection_reason)->toBe('face_too_small');
});

it('prefers search priority media over index only media when distances tie', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'top_k' => 10,
        'search_threshold' => 0.4,
    ]);

    bindSingleFaceSearchProviders([0.10, 0.20, 0.30]);

    $priorityMedia = EventMedia::factory()->approved()->published()->create([
        'event_id' => $event->id,
    ]);
    $indexOnlyMedia = EventMedia::factory()->approved()->published()->create([
        'event_id' => $event->id,
    ]);

    $store = new class($priorityMedia->id, $indexOnlyMedia->id) implements FaceVectorStoreInterface
    {
        public function __construct(
            private readonly int $priorityMediaId,
            private readonly int $indexOnlyMediaId,
        ) {}

        public function upsert(EventMediaFace $face, FaceEmbeddingData $embedding): EventMediaFace
        {
            return $face;
        }

        public function delete(EventMediaFace $face): void {}

        public function search(
            int $eventId,
            array $queryEmbedding,
            int $topK,
            ?float $threshold = null,
            bool $searchableOnly = true,
            ?string $searchStrategy = null,
        ): array {
            return [
                new \App\Modules\FaceSearch\DTOs\FaceSearchMatchData(
                    faceId: 9001,
                    eventMediaId: $this->indexOnlyMediaId,
                    distance: 0.10,
                    qualityScore: 0.95,
                    faceAreaRatio: 0.18,
                    qualityTier: 'index_only',
                ),
                new \App\Modules\FaceSearch\DTOs\FaceSearchMatchData(
                    faceId: 9002,
                    eventMediaId: $this->priorityMediaId,
                    distance: 0.10,
                    qualityScore: 0.72,
                    faceAreaRatio: 0.09,
                    qualityTier: 'search_priority',
                ),
            ];
        }
    };

    app()->instance(FaceVectorStoreInterface::class, $store);

    $response = $this->withHeaders(['Accept' => 'application/json'])->post(
        "/api/v1/events/{$event->id}/face-search/search",
        [
            'selfie' => UploadedFile::fake()->image('selfie.jpg'),
            'include_pending' => true,
        ],
    );

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.results.0.event_media_id', $priorityMedia->id);
    $response->assertJsonPath('data.results.0.best_quality_tier', 'search_priority');
    $response->assertJsonPath('data.results.1.event_media_id', $indexOnlyMedia->id);
    $response->assertJsonPath('data.results.1.best_quality_tier', 'index_only');
});

it('blocks public selfie search when the event does not allow it', function () {
    $event = Event::factory()->active()->create();

    EventModule::create([
        'event_id' => $event->id,
        'module_key' => 'live',
        'is_enabled' => true,
    ]);

    \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'allow_public_selfie_search' => false,
    ]);

    $response = $this->withHeaders(['Accept' => 'application/json'])->post(
        "/api/v1/public/events/{$event->slug}/face-search/search",
        [
            'selfie' => UploadedFile::fake()->image('selfie.jpg'),
            'consent_accepted' => '1',
            'consent_version' => 'v1',
        ],
    );

    $this->assertApiError($response, 422);
    $response->assertJsonPath('message', 'A busca publica por selfie nao esta disponivel para este evento.');
});

it('requires explicit consent in public selfie search', function () {
    $event = Event::factory()->active()->create();

    EventModule::create([
        'event_id' => $event->id,
        'module_key' => 'live',
        'is_enabled' => true,
    ]);

    \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'allow_public_selfie_search' => true,
    ]);

    $response = $this->withHeaders(['Accept' => 'application/json'])->post(
        "/api/v1/public/events/{$event->slug}/face-search/search",
        [
            'selfie' => UploadedFile::fake()->image('selfie.jpg'),
            'consent_version' => 'v1',
        ],
    );

    $this->assertApiValidationError($response, ['consent_accepted']);
});

it('returns only approved and published media in public selfie search and stores retention metadata', function () {
    $event = Event::factory()->active()->create();

    EventModule::create([
        'event_id' => $event->id,
        'module_key' => 'live',
        'is_enabled' => true,
    ]);

    \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'allow_public_selfie_search' => true,
        'selfie_retention_hours' => 12,
        'top_k' => 10,
        'search_threshold' => 0.4,
    ]);

    bindSingleFaceSearchProviders([0.20, 0.10, 0.40]);

    $publishedMedia = EventMedia::factory()->approved()->published()->create([
        'event_id' => $event->id,
    ]);
    $approvedDraftMedia = EventMedia::factory()->approved()->create([
        'event_id' => $event->id,
        'publication_status' => PublicationStatus::Draft->value,
    ]);
    $pendingMedia = EventMedia::factory()->create([
        'event_id' => $event->id,
        'moderation_status' => ModerationStatus::Pending->value,
        'publication_status' => PublicationStatus::Draft->value,
    ]);

    seedFaceEmbedding($event->id, $publishedMedia->id, [0.20, 0.10, 0.40]);
    seedFaceEmbedding($event->id, $approvedDraftMedia->id, [0.20, 0.10, 0.40]);
    seedFaceEmbedding($event->id, $pendingMedia->id, [0.20, 0.10, 0.40]);

    $response = $this->withHeaders(['Accept' => 'application/json'])->post(
        "/api/v1/public/events/{$event->slug}/face-search/search",
        [
            'selfie' => UploadedFile::fake()->image('selfie.jpg'),
            'consent_accepted' => '1',
            'consent_version' => 'v1',
            'selfie_storage_strategy' => 'memory_only',
        ],
    );

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.total_results', 1);
    $response->assertJsonPath('data.results.0.event_media_id', $publishedMedia->id);
    $response->assertJsonPath('data.request.consent_version', 'v1');
    $response->assertJsonPath('data.request.selfie_storage_strategy', 'memory_only');

    $request = EventFaceSearchRequest::query()->latest('id')->first();

    expect($request)->not->toBeNull()
        ->and($request?->status)->toBe('completed')
        ->and($request?->result_photo_ids_json)->toBe([$publishedMedia->id])
        ->and($request?->expires_at)->not->toBeNull()
        ->and($request?->expires_at?->greaterThan(now()->addHours(11)))->toBeTrue();
});

function bindSingleFaceSearchProviders(array $vector): void
{
    app()->instance(FaceDetectionProviderInterface::class, new class implements FaceDetectionProviderInterface
    {
        public function detect(\App\Modules\MediaProcessing\Models\EventMedia $media, \App\Modules\FaceSearch\Models\EventFaceSearchSetting $settings, string $binary): array
        {
            return [
                new DetectedFaceData(
                    boundingBox: new FaceBoundingBoxData(24, 24, 180, 180),
                    detectionConfidence: 0.99,
                    qualityScore: 0.92,
                    sharpnessScore: 0.90,
                    faceAreaRatio: 0.12,
                    isPrimaryCandidate: true,
                ),
            ];
        }
    });

    app()->instance(FaceEmbeddingProviderInterface::class, new class($vector) implements FaceEmbeddingProviderInterface
    {
        public function __construct(private readonly array $vector) {}

        public function embed(
            \App\Modules\MediaProcessing\Models\EventMedia $media,
            \App\Modules\FaceSearch\Models\EventFaceSearchSetting $settings,
            string $cropBinary,
            DetectedFaceData $face,
        ): FaceEmbeddingData {
            return new FaceEmbeddingData(
                vector: $this->vector,
                providerKey: 'noop',
                providerVersion: 'foundation-v1',
                modelKey: 'face-embedding-foundation-v1',
                modelSnapshot: 'face-embedding-foundation-v1',
                embeddingVersion: 'foundation-v1',
            );
        }
    });
}

function seedFaceEmbedding(int $eventId, int $eventMediaId, array $vector): void
{
    $face = \Database\Factories\EventMediaFaceFactory::new()->create([
        'event_id' => $eventId,
        'event_media_id' => $eventMediaId,
        'searchable' => true,
        'embedding' => null,
    ]);

    app(FaceVectorStoreInterface::class)->upsert($face, new FaceEmbeddingData(
        vector: $vector,
        providerKey: 'noop',
        providerVersion: 'foundation-v1',
        modelKey: 'face-embedding-foundation-v1',
        modelSnapshot: 'face-embedding-foundation-v1',
        embeddingVersion: 'foundation-v1',
    ));
}
