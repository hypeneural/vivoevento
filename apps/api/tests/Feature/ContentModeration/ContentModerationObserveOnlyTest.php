<?php

use App\Modules\ContentModeration\DTOs\ContentSafetyEvaluationResult;
use App\Modules\ContentModeration\Jobs\AnalyzeContentSafetyJob;
use App\Modules\ContentModeration\Models\EventContentModerationSetting;
use App\Modules\ContentModeration\Services\ContentModerationProviderInterface;
use App\Modules\Events\Models\Event;
use App\Modules\MediaIntelligence\Jobs\EvaluateMediaPromptJob;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Jobs\PublishMediaJob;
use App\Modules\MediaProcessing\Jobs\RunModerationJob;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Support\Facades\Queue;

it('approves media when observe_only receives review from safety', function () {
    Queue::fake();

    app()->bind(ContentModerationProviderInterface::class, fn () => new class implements ContentModerationProviderInterface
    {
        public function evaluate(
            EventMedia $media,
            EventContentModerationSetting $settings,
        ): ContentSafetyEvaluationResult {
            return ContentSafetyEvaluationResult::review(
                categoryScores: ['nudity' => 0.71],
                reasonCodes: ['nudity.review'],
                rawResponse: ['provider' => 'fake-safety'],
                providerKey: 'fake-safety',
                providerVersion: 'test-v1',
                modelKey: 'fake-model',
                modelSnapshot: 'fake-model@2026-04-07',
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
        'mode' => 'observe_only',
        'enabled' => true,
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'safety_status' => 'queued',
        'vlm_status' => 'skipped',
    ]);

    app(AnalyzeContentSafetyJob::class, ['eventMediaId' => $media->id])->handle();
    app(RunModerationJob::class, ['eventMediaId' => $media->id])->handle();

    $media->refresh();

    expect($media->safety_status)->toBe('review')
        ->and($media->moderation_status)->toBe(ModerationStatus::Approved);

    Queue::assertPushed(PublishMediaJob::class, fn (PublishMediaJob $job) => $job->eventMediaId === $media->id);
});

it('approves media when observe_only receives block from safety', function () {
    Queue::fake();

    app()->bind(ContentModerationProviderInterface::class, fn () => new class implements ContentModerationProviderInterface
    {
        public function evaluate(
            EventMedia $media,
            EventContentModerationSetting $settings,
        ): ContentSafetyEvaluationResult {
            return ContentSafetyEvaluationResult::block(
                categoryScores: ['nudity' => 0.99],
                reasonCodes: ['nudity.block'],
                rawResponse: ['provider' => 'fake-safety'],
                providerKey: 'fake-safety',
                providerVersion: 'test-v1',
                modelKey: 'fake-model',
                modelSnapshot: 'fake-model@2026-04-07',
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
        'mode' => 'observe_only',
        'enabled' => true,
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'safety_status' => 'queued',
        'vlm_status' => 'skipped',
    ]);

    app(AnalyzeContentSafetyJob::class, ['eventMediaId' => $media->id])->handle();
    app(RunModerationJob::class, ['eventMediaId' => $media->id])->handle();

    $media->refresh();

    expect($media->safety_status)->toBe('block')
        ->and($media->moderation_status)->toBe(ModerationStatus::Approved);

    Queue::assertPushed(PublishMediaJob::class, fn (PublishMediaJob $job) => $job->eventMediaId === $media->id);
});

it('approves media when observe_only safety fails technically', function () {
    Queue::fake();

    app()->bind(ContentModerationProviderInterface::class, fn () => new class implements ContentModerationProviderInterface
    {
        public function evaluate(
            EventMedia $media,
            EventContentModerationSetting $settings,
        ): ContentSafetyEvaluationResult {
            throw new \RuntimeException('provider timeout');
        }
    });

    $event = Event::factory()->active()->create([
        'moderation_mode' => 'ai',
    ]);

    EventContentModerationSetting::factory()->create([
        'event_id' => $event->id,
        'provider_key' => 'fake-safety',
        'mode' => 'observe_only',
        'fallback_mode' => 'block',
        'enabled' => true,
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'safety_status' => 'queued',
        'vlm_status' => 'skipped',
    ]);

    app(AnalyzeContentSafetyJob::class, ['eventMediaId' => $media->id])->handle();
    app(RunModerationJob::class, ['eventMediaId' => $media->id])->handle();

    $media->refresh();

    expect($media->safety_status)->toBe('block')
        ->and($media->moderation_status)->toBe(ModerationStatus::Approved);
});

it('still dispatches vlm and waits for gate completion when safety is observe_only', function () {
    Queue::fake();

    app()->bind(ContentModerationProviderInterface::class, fn () => new class implements ContentModerationProviderInterface
    {
        public function evaluate(
            EventMedia $media,
            EventContentModerationSetting $settings,
        ): ContentSafetyEvaluationResult {
            return ContentSafetyEvaluationResult::review(
                categoryScores: ['nudity' => 0.71],
                reasonCodes: ['nudity.review'],
                rawResponse: ['provider' => 'fake-safety'],
                providerKey: 'fake-safety',
                providerVersion: 'test-v1',
                modelKey: 'fake-model',
                modelSnapshot: 'fake-model@2026-04-07',
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
        'mode' => 'observe_only',
        'enabled' => true,
    ]);

    \Database\Factories\EventMediaIntelligenceSettingFactory::new()->gate()->create([
        'event_id' => $event->id,
        'enabled' => true,
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'safety_status' => 'queued',
        'vlm_status' => 'queued',
    ]);

    app(AnalyzeContentSafetyJob::class, ['eventMediaId' => $media->id])->handle();

    Queue::assertPushed(EvaluateMediaPromptJob::class, fn (EvaluateMediaPromptJob $job) => $job->eventMediaId === $media->id);
    Queue::assertNotPushed(RunModerationJob::class);
});
