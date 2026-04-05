<?php

use App\Modules\ContentModeration\DTOs\ContentSafetyEvaluationResult;
use App\Modules\ContentModeration\Jobs\AnalyzeContentSafetyJob;
use App\Modules\ContentModeration\Models\EventContentModerationSetting;
use App\Modules\ContentModeration\Models\EventMediaSafetyEvaluation;
use App\Modules\ContentModeration\Services\ContentModerationProviderInterface;
use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\DTOs\FaceBoundingBoxData;
use App\Modules\FaceSearch\DTOs\FaceEmbeddingData;
use App\Modules\FaceSearch\Jobs\IndexMediaFacesJob;
use App\Modules\FaceSearch\Models\EventMediaFace;
use App\Modules\FaceSearch\Services\FaceDetectionProviderInterface;
use App\Modules\FaceSearch\Services\FaceEmbeddingProviderInterface;
use App\Modules\MediaIntelligence\DTOs\VisualReasoningEvaluationResult;
use App\Modules\MediaIntelligence\Jobs\EvaluateMediaPromptJob;
use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;
use App\Modules\MediaIntelligence\Models\EventMediaVlmEvaluation;
use App\Modules\MediaIntelligence\Services\VisualReasoningProviderInterface;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Models\EventMediaVariant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

it('reprocesses safety for a media item and preserves evaluation history', function () {
    Queue::fake();

    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'moderation_mode' => 'ai',
    ]);

    EventContentModerationSetting::factory()->create([
        'event_id' => $event->id,
        'provider_key' => 'fake-safety',
        'enabled' => true,
        'threshold_version' => 'policy-v3',
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'safety_status' => 'review',
    ]);

    EventMediaSafetyEvaluation::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'decision' => 'review',
    ]);

    app()->bind(ContentModerationProviderInterface::class, fn () => new class implements ContentModerationProviderInterface
    {
        public function evaluate(EventMedia $media, EventContentModerationSetting $settings): ContentSafetyEvaluationResult
        {
            return ContentSafetyEvaluationResult::pass(
                categoryScores: ['nudity' => 0.01, 'violence' => 0.02, 'self_harm' => 0.0],
                reasonCodes: [],
                rawResponse: ['provider' => 'fake-safety'],
                providerKey: 'fake-safety',
                providerVersion: 'test-v2',
                modelKey: 'fake-safety-model',
                modelSnapshot: 'fake-safety-model@2',
                thresholdVersion: $settings->threshold_version,
            );
        }
    });

    $response = $this->apiPost("/media/{$media->id}/reprocess/safety", [
        'reason' => 'Atualizar thresholds',
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.safety_status', 'queued');

    Queue::assertPushed(AnalyzeContentSafetyJob::class, fn (AnalyzeContentSafetyJob $job) => $job->eventMediaId === $media->id);

    app(AnalyzeContentSafetyJob::class, ['eventMediaId' => $media->id])->handle();

    $media->refresh();

    expect($media->safety_status)->toBe('pass')
        ->and(EventMediaSafetyEvaluation::query()->where('event_media_id', $media->id)->count())->toBe(2);

    $activity = Activity::query()
        ->where('subject_type', EventMedia::class)
        ->where('subject_id', $media->id)
        ->where('event', 'media.reprocess_requested')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity?->properties['attributes']['stage'] ?? null)->toBe('safety')
        ->and($activity?->causer_id)->toBe($user->id);
});

it('reprocesses vlm for a media item and persists a new evaluation', function () {
    Queue::fake();

    [, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'moderation_mode' => 'manual',
    ]);

    EventMediaIntelligenceSetting::factory()->create([
        'event_id' => $event->id,
        'enabled' => true,
        'mode' => 'enrich_only',
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'vlm_status' => 'completed',
    ]);

    EventMediaVlmEvaluation::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'decision' => 'approve',
    ]);

    EventMediaVariant::query()->create([
        'event_media_id' => $media->id,
        'variant_key' => 'fast_preview',
        'disk' => 'public',
        'path' => "events/{$event->id}/variants/{$media->id}/fast_preview.webp",
        'mime_type' => 'image/webp',
        'width' => 512,
        'height' => 512,
        'size_bytes' => 2048,
    ]);

    app()->bind(VisualReasoningProviderInterface::class, fn () => new class implements VisualReasoningProviderInterface
    {
        public function evaluate(EventMedia $media, EventMediaIntelligenceSetting $settings): VisualReasoningEvaluationResult
        {
            return VisualReasoningEvaluationResult::approve(
                reason: 'Cena ainda compativel com o evento.',
                shortCaption: 'Reprocessado com legenda nova.',
                tags: ['evento', 'reprocessado'],
                rawResponse: ['provider' => 'fake-vlm'],
                providerKey: 'fake-vlm',
                providerVersion: 'test-v2',
                modelKey: 'fake-vlm-model',
                modelSnapshot: 'fake-vlm-model@2',
                promptVersion: $settings->prompt_version,
                responseSchemaVersion: $settings->response_schema_version,
                modeApplied: $settings->mode,
                tokensInput: 80,
                tokensOutput: 18,
            );
        }
    });

    $response = $this->apiPost("/media/{$media->id}/reprocess/vlm", [
        'reason' => 'Troca de prompt',
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.vlm_status', 'queued');

    Queue::assertPushed(EvaluateMediaPromptJob::class, fn (EvaluateMediaPromptJob $job) => $job->eventMediaId === $media->id);

    app(EvaluateMediaPromptJob::class, ['eventMediaId' => $media->id])->handle();

    $media->refresh();

    expect($media->vlm_status)->toBe('completed')
        ->and($media->caption)->toBe('Reprocessado com legenda nova.')
        ->and(EventMediaVlmEvaluation::query()->where('event_media_id', $media->id)->count())->toBe(2);
});

it('reprocesses face index and rewrites the stored face projection', function () {
    Queue::fake();
    Storage::fake('public');
    Storage::fake('ai-private');

    [, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
    ]);

    $galleryPath = UploadedFile::fake()
        ->image('gallery.jpg', 1200, 900)
        ->store("events/{$event->id}/variants/{$event->id}", 'public');

    $media = EventMedia::factory()->approved()->create([
        'event_id' => $event->id,
        'face_index_status' => 'indexed',
    ]);

    EventMediaVariant::query()->create([
        'event_media_id' => $media->id,
        'variant_key' => 'gallery',
        'disk' => 'public',
        'path' => $galleryPath,
        'width' => 1200,
        'height' => 900,
        'size_bytes' => Storage::disk('public')->size($galleryPath),
        'mime_type' => 'image/jpeg',
    ]);

    $oldCrop = "events/{$event->id}/faces/{$media->id}/face-legacy.webp";
    Storage::disk('ai-private')->put($oldCrop, 'legacy-face');

    $oldFace = \Database\Factories\EventMediaFaceFactory::new()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'face_index' => 9,
        'crop_disk' => 'ai-private',
        'crop_path' => $oldCrop,
        'embedding' => '[0.9,0.8,0.7]',
    ]);

    app()->instance(FaceDetectionProviderInterface::class, new class implements FaceDetectionProviderInterface
    {
        public function detect(EventMedia $media, \App\Modules\FaceSearch\Models\EventFaceSearchSetting $settings, string $binary): array
        {
            return [
                new DetectedFaceData(
                    boundingBox: new FaceBoundingBoxData(60, 80, 200, 200),
                    detectionConfidence: 0.99,
                    qualityScore: 0.94,
                    sharpnessScore: 0.88,
                    faceAreaRatio: 0.15,
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
                vector: [0.11, 0.22, 0.33],
                providerKey: 'noop',
                providerVersion: 'foundation-v1',
                modelKey: 'face-embedding-foundation-v1',
                modelSnapshot: 'face-embedding-foundation-v1@2',
                embeddingVersion: 'foundation-v2',
            );
        }
    });

    $response = $this->apiPost("/media/{$media->id}/reprocess/face_index");

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.face_index_status', 'queued');

    Queue::assertPushed(IndexMediaFacesJob::class, fn (IndexMediaFacesJob $job) => $job->eventMediaId === $media->id);

    app(IndexMediaFacesJob::class, ['eventMediaId' => $media->id])->handle();

    $media->refresh();
    $faces = EventMediaFace::query()->where('event_media_id', $media->id)->get();

    expect($media->face_index_status)->toBe('indexed')
        ->and($faces)->toHaveCount(1)
        ->and($faces->first()->id)->not->toBe($oldFace->id)
        ->and($faces->first()->embedding_version)->toBe('foundation-v2');

    Storage::disk('ai-private')->assertMissing($oldCrop);
    Storage::disk('ai-private')->assertExists("events/{$event->id}/faces/{$media->id}/face-0.webp");
});

it('forbids reprocessing media when the user lacks event access', function () {
    [, $organization] = $this->actingAsViewer();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
    ]);

    $response = $this->apiPost("/media/{$media->id}/reprocess/safety");

    $this->assertApiForbidden($response);
});
