<?php

use App\Modules\ContentModeration\DTOs\ContentSafetyEvaluationResult;
use App\Modules\ContentModeration\Jobs\AnalyzeContentSafetyJob;
use App\Modules\ContentModeration\Models\EventContentModerationSetting;
use App\Modules\ContentModeration\Services\ContentModerationProviderInterface;
use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Jobs\IndexMediaFacesJob;
use App\Modules\MediaIntelligence\Jobs\EvaluateMediaPromptJob;
use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Jobs\PublishMediaJob;
use App\Modules\MediaProcessing\Jobs\RunModerationJob;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Models\EventMediaVariant;
use App\Modules\MediaProcessing\Models\MediaProcessingRun;
use Illuminate\Support\Facades\Queue;

it('falls back to review when media safety is degraded operationally', function () {
    Queue::fake();

    config()->set('observability.degradation.media_safety_mode', 'review');

    app()->bind(ContentModerationProviderInterface::class, fn () => new class implements ContentModerationProviderInterface
    {
        public function evaluate(EventMedia $media, EventContentModerationSetting $settings): ContentSafetyEvaluationResult
        {
            throw new RuntimeException('provider should not run while degraded');
        }
    });

    $event = Event::factory()->active()->create([
        'moderation_mode' => 'ai',
    ]);

    EventContentModerationSetting::factory()->create([
        'event_id' => $event->id,
        'provider_key' => 'openai',
        'enabled' => true,
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'safety_status' => 'queued',
        'vlm_status' => 'queued',
    ]);

    app(AnalyzeContentSafetyJob::class, ['eventMediaId' => $media->id])->handle();

    $media->refresh();

    expect($media->safety_status)->toBe('review');

    $run = MediaProcessingRun::query()
        ->where('event_media_id', $media->id)
        ->where('stage_key', 'safety')
        ->latest('id')
        ->first();

    expect($run)->not->toBeNull()
        ->and($run?->status)->toBe('completed')
        ->and($run?->decision_key)->toBe('review')
        ->and($run?->result_json['degraded'] ?? null)->toBeTrue();

    Queue::assertPushed(RunModerationJob::class, fn (RunModerationJob $job) => $job->eventMediaId === $media->id);
    Queue::assertNotPushed(EvaluateMediaPromptJob::class);
});

it('skips vlm dispatch when operational degradation disables the lane', function () {
    Queue::fake();

    config()->set('observability.degradation.media_vlm_enabled', false);

    app()->bind(ContentModerationProviderInterface::class, fn () => new class implements ContentModerationProviderInterface
    {
        public function evaluate(EventMedia $media, EventContentModerationSetting $settings): ContentSafetyEvaluationResult
        {
            return ContentSafetyEvaluationResult::pass(
                categoryScores: ['nudity' => 0.01],
                reasonCodes: [],
                rawResponse: ['provider' => 'fake-safety'],
                providerKey: 'fake-safety',
                providerVersion: 'test-v1',
                modelKey: 'fake-safety-model',
                modelSnapshot: 'fake-safety-model@1',
                thresholdVersion: $settings->threshold_version,
            );
        }
    });

    $event = Event::factory()->active()->create([
        'moderation_mode' => 'ai',
    ]);

    EventContentModerationSetting::factory()->create([
        'event_id' => $event->id,
        'provider_key' => 'fake-safety',
        'enabled' => true,
    ]);

    EventMediaIntelligenceSetting::factory()->gate()->create([
        'event_id' => $event->id,
        'enabled' => true,
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'safety_status' => 'queued',
        'vlm_status' => 'queued',
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

    app(AnalyzeContentSafetyJob::class, ['eventMediaId' => $media->id])->handle();

    $media->refresh();

    expect($media->safety_status)->toBe('pass')
        ->and($media->vlm_status)->toBe('skipped');

    Queue::assertNotPushed(EvaluateMediaPromptJob::class);
    Queue::assertPushed(RunModerationJob::class, fn (RunModerationJob $job) => $job->eventMediaId === $media->id);
});

it('skips face indexing dispatch when operational degradation disables the lane', function () {
    Queue::fake();

    config()->set('observability.degradation.face_index_enabled', false);

    $event = Event::factory()->active()->create([
        'moderation_mode' => 'none',
    ]);

    \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'moderation_status' => ModerationStatus::Pending->value,
        'face_index_status' => 'queued',
    ]);

    app(RunModerationJob::class, ['eventMediaId' => $media->id])->handle();

    $media->refresh();

    expect($media->moderation_status)->toBe(ModerationStatus::Approved)
        ->and($media->face_index_status)->toBe('skipped');

    Queue::assertPushed(PublishMediaJob::class, fn (PublishMediaJob $job) => $job->eventMediaId === $media->id);
    Queue::assertNotPushed(IndexMediaFacesJob::class);
});
