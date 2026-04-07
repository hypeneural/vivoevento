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
use App\Modules\MediaProcessing\Services\MediaAssetUrlService;
use App\Modules\MediaProcessing\Models\MediaProcessingRun;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

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

it('persists a safety evaluation using data url fallback when preview is not publicly accessible', function () {
    Queue::fake();
    Storage::fake('public');

    config()->set('content_moderation.providers.openai.api_key', 'test-key');

    Http::fake([
        'https://api.openai.com/v1/moderations' => Http::response([
            'id' => 'modr_data_url_pipeline',
            'model' => 'omni-moderation-latest',
            'results' => [[
                'flagged' => false,
                'categories' => [
                    'violence' => false,
                ],
                'category_scores' => [
                    'sexual' => 0.02,
                    'sexual/minors' => 0.00,
                    'violence' => 0.03,
                    'violence/graphic' => 0.00,
                    'self-harm' => 0.00,
                    'self-harm/intent' => 0.00,
                    'self-harm/instructions' => 0.00,
                ],
                'category_applied_input_types' => [
                    'violence' => ['image'],
                ],
            ]],
        ]),
    ]);

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
        'original_path' => "events/{$event->id}/originals/fallback.jpg",
    ]);

    $media->variants()->create([
        'variant_key' => 'fast_preview',
        'disk' => 'public',
        'path' => "events/{$event->id}/variants/{$media->id}/fast_preview.webp",
        'mime_type' => 'image/webp',
    ]);

    Storage::disk('public')->put(
        "events/{$event->id}/variants/{$media->id}/fast_preview.webp",
        'fake-image-binary'
    );

    $assetUrls = \Mockery::mock(MediaAssetUrlService::class)->shouldIgnoreMissing();
    $assetUrls->shouldReceive('preview')->andReturnNull();
    app()->instance(MediaAssetUrlService::class, $assetUrls);

    app(AnalyzeContentSafetyJob::class, ['eventMediaId' => $media->id])->handle();

    $media->refresh();
    $evaluation = EventMediaSafetyEvaluation::query()
        ->where('event_media_id', $media->id)
        ->latest('id')
        ->first();

    expect($media->safety_status)->toBe('pass')
        ->and($evaluation)->not->toBeNull()
        ->and(data_get($evaluation?->raw_response_json, 'input_path_used'))->toBe('data_url')
        ->and(data_get($evaluation?->normalized_provider_json, 'input_path_used'))->toBe('data_url');
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
