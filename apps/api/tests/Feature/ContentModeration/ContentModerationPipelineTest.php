<?php

use App\Modules\ContentModeration\DTOs\ContentSafetyEvaluationResult;
use App\Modules\ContentModeration\Jobs\AnalyzeContentSafetyJob;
use App\Modules\ContentModeration\Models\EventContentModerationSetting;
use App\Modules\ContentModeration\Models\EventMediaSafetyEvaluation;
use App\Modules\ContentModeration\Services\ContentModerationProviderInterface;
use App\Modules\Events\Models\Event;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Jobs\PublishMediaJob;
use App\Modules\MediaProcessing\Jobs\RunModerationJob;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Models\MediaProcessingRun;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('skips safety analysis when an event has no moderation settings and continues the pipeline', function () {
    Queue::fake();

    $event = Event::factory()->active()->create([
        'moderation_mode' => 'none',
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'safety_status' => 'queued',
    ]);

    app(AnalyzeContentSafetyJob::class, ['eventMediaId' => $media->id])->handle();

    $media->refresh();

    expect($media->safety_status)->toBe('skipped');

    $run = MediaProcessingRun::query()
        ->where('event_media_id', $media->id)
        ->where('stage_key', 'safety')
        ->latest('id')
        ->first();

    expect($run)->not->toBeNull()
        ->and($run?->status)->toBe('completed')
        ->and($run?->decision_key)->toBe('skipped');

    Queue::assertPushed(RunModerationJob::class, fn (RunModerationJob $job) => $job->eventMediaId === $media->id);
});

it('persists a safety evaluation when content moderation is enabled', function () {
    Queue::fake();

    app()->bind(ContentModerationProviderInterface::class, fn () => new class implements ContentModerationProviderInterface
    {
        public function evaluate(
            EventMedia $media,
            EventContentModerationSetting $settings,
        ): ContentSafetyEvaluationResult {
            return ContentSafetyEvaluationResult::review(
                categoryScores: [
                    'nudity' => 0.74,
                    'violence' => 0.08,
                ],
                reasonCodes: ['nudity.review'],
                rawResponse: [
                    'provider' => 'fake-safety',
                ],
                providerKey: 'fake-safety',
                providerVersion: 'test-v1',
                modelKey: 'fake-model',
                modelSnapshot: 'fake-model@2026-04-01',
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
        'threshold_version' => 'policy-v2',
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'safety_status' => 'queued',
    ]);

    app(AnalyzeContentSafetyJob::class, ['eventMediaId' => $media->id])->handle();

    $media->refresh();

    expect($media->safety_status)->toBe('review');

    $evaluation = EventMediaSafetyEvaluation::query()
        ->where('event_media_id', $media->id)
        ->latest('id')
        ->first();

    expect($evaluation)->not->toBeNull()
        ->and($evaluation?->decision)->toBe('review')
        ->and($evaluation?->review_required)->toBeTrue()
        ->and($evaluation?->threshold_version)->toBe('policy-v2');

    Queue::assertPushed(RunModerationJob::class, fn (RunModerationJob $job) => $job->eventMediaId === $media->id);
});

it('skips provider execution when the event is not in ai moderation mode', function () {
    Queue::fake();
    Http::fake();

    $event = Event::factory()->active()->create([
        'moderation_mode' => 'none',
    ]);

    EventContentModerationSetting::factory()->create([
        'event_id' => $event->id,
        'provider_key' => 'openai',
        'enabled' => true,
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'safety_status' => 'queued',
    ]);

    app(AnalyzeContentSafetyJob::class, ['eventMediaId' => $media->id])->handle();
    app(RunModerationJob::class, ['eventMediaId' => $media->id])->handle();

    $media->refresh();

    expect($media->safety_status)->toBe('skipped')
        ->and($media->moderation_status)->toBe(ModerationStatus::Approved);

    Http::assertNothingSent();
});

it('rejects media in ai moderation mode when safety returns block', function () {
    Queue::fake();
    Http::fake([
        'https://api.openai.com/v1/moderations' => Http::response([
            'id' => 'modr_block',
            'model' => 'omni-moderation-latest',
            'results' => [[
                'flagged' => true,
                'category_scores' => [
                    'sexual' => 0.98,
                    'sexual/minors' => 0.00,
                    'violence' => 0.10,
                    'violence/graphic' => 0.05,
                    'self-harm' => 0.01,
                    'self-harm/intent' => 0.00,
                    'self-harm/instructions' => 0.00,
                ],
            ]],
        ]),
    ]);

    config()->set('content_moderation.providers.openai.api_key', 'test-key');

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
        'original_disk' => 'public',
        'original_path' => 'events/' . $event->id . '/originals/block.jpg',
    ]);

    app(AnalyzeContentSafetyJob::class, ['eventMediaId' => $media->id])->handle();
    app(RunModerationJob::class, ['eventMediaId' => $media->id])->handle();

    $media->refresh();

    expect($media->safety_status)->toBe('block')
        ->and($media->moderation_status)->toBe(ModerationStatus::Rejected);
});

it('marks safety as failed when the provider request fails and keeps media pending', function () {
    Queue::fake();
    Http::fake(fn () => throw new ConnectionException('timeout'));

    config()->set('content_moderation.providers.openai.api_key', 'test-key');

    $event = Event::factory()->active()->create([
        'moderation_mode' => 'ai',
    ]);

    EventContentModerationSetting::factory()->create([
        'event_id' => $event->id,
        'provider_key' => 'openai',
        'enabled' => true,
        'fallback_mode' => 'review',
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'safety_status' => 'queued',
        'original_disk' => 'public',
        'original_path' => 'events/' . $event->id . '/originals/fail.jpg',
    ]);

    app(AnalyzeContentSafetyJob::class, ['eventMediaId' => $media->id])->handle();
    app(RunModerationJob::class, ['eventMediaId' => $media->id])->handle();

    $media->refresh();

    expect($media->safety_status)->toBe('failed')
        ->and($media->moderation_status)->toBe(ModerationStatus::Pending);
});

it('keeps media pending when safety requires review and does not queue publication', function () {
    Queue::fake();

    $event = Event::factory()->active()->create([
        'moderation_mode' => 'ai',
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'moderation_status' => ModerationStatus::Pending->value,
        'safety_status' => 'review',
        'vlm_status' => 'skipped',
    ]);

    app(RunModerationJob::class, ['eventMediaId' => $media->id])->handle();

    $media->refresh();

    expect($media->moderation_status)->toBe(ModerationStatus::Pending);

    Queue::assertNotPushed(PublishMediaJob::class);
});
