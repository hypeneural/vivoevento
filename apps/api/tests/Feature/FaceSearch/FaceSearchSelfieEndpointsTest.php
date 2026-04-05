<?php

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
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Http\UploadedFile;

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
