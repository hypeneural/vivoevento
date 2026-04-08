<?php

use App\Modules\ContentModeration\DTOs\ContentSafetyEvaluationResult;
use App\Modules\ContentModeration\Models\EventContentModerationSetting;
use App\Modules\ContentModeration\Services\ContentModerationProviderInterface;
use App\Modules\Events\Models\Event;
use App\Modules\MediaIntelligence\DTOs\VisualReasoningEvaluationResult;
use App\Modules\MediaIntelligence\Jobs\EvaluateMediaPromptJob;
use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;
use App\Modules\MediaIntelligence\Models\EventMediaVlmEvaluation;
use App\Modules\MediaIntelligence\Services\VisualReasoningProviderInterface;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Jobs\PublishMediaJob;
use App\Modules\MediaProcessing\Jobs\RunModerationJob;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Models\EventMediaVariant;
use App\Modules\MediaProcessing\Models\MediaProcessingRun;
use Illuminate\Support\Facades\Queue;

it('dispatches vlm and finalizes moderation immediately when media intelligence is enrich_only', function () {
    Queue::fake();

    app()->bind(ContentModerationProviderInterface::class, fn () => new class implements ContentModerationProviderInterface
    {
        public function evaluate(EventMedia $media, EventContentModerationSetting $settings): ContentSafetyEvaluationResult
        {
            return ContentSafetyEvaluationResult::pass(
                categoryScores: ['nudity' => 0.01, 'violence' => 0.01, 'self_harm' => 0.0],
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
        'moderation_mode' => 'none',
    ]);

    EventMediaIntelligenceSetting::factory()->create([
        'event_id' => $event->id,
        'enabled' => true,
        'mode' => 'enrich_only',
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

    app(\App\Modules\ContentModeration\Jobs\AnalyzeContentSafetyJob::class, ['eventMediaId' => $media->id])->handle();

    Queue::assertPushed(EvaluateMediaPromptJob::class, fn (EvaluateMediaPromptJob $job) => $job->eventMediaId === $media->id);
    Queue::assertPushed(RunModerationJob::class, fn (RunModerationJob $job) => $job->eventMediaId === $media->id);
});

it('waits for vlm completion before finalizing moderation when gate mode is enabled', function () {
    Queue::fake();

    app()->bind(ContentModerationProviderInterface::class, fn () => new class implements ContentModerationProviderInterface
    {
        public function evaluate(EventMedia $media, EventContentModerationSetting $settings): ContentSafetyEvaluationResult
        {
            return ContentSafetyEvaluationResult::pass(
                categoryScores: ['nudity' => 0.01, 'violence' => 0.01, 'self_harm' => 0.0],
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

    app(\App\Modules\ContentModeration\Jobs\AnalyzeContentSafetyJob::class, ['eventMediaId' => $media->id])->handle();

    Queue::assertPushed(EvaluateMediaPromptJob::class, fn (EvaluateMediaPromptJob $job) => $job->eventMediaId === $media->id);
    Queue::assertNotPushed(RunModerationJob::class);
});

it('persists a vlm evaluation and completes the stage when the provider succeeds', function () {
    Queue::fake();

    app()->bind(VisualReasoningProviderInterface::class, fn () => new class implements VisualReasoningProviderInterface
    {
        public function evaluate(EventMedia $media, EventMediaIntelligenceSetting $settings): VisualReasoningEvaluationResult
        {
            return VisualReasoningEvaluationResult::approve(
                reason: 'Imagem compativel com o evento.',
                shortCaption: 'Entrada especial na festa.',
                replyText: 'Memorias que fazem o coracao sorrir! 🎉📸',
                tags: ['festa', 'retrato'],
                rawResponse: ['provider' => 'fake-vlm'],
                providerKey: 'fake-vlm',
                providerVersion: 'test-v1',
                modelKey: 'fake-vlm-model',
                modelSnapshot: 'fake-vlm-model@1',
                promptVersion: $settings->prompt_version,
                responseSchemaVersion: $settings->response_schema_version,
                modeApplied: $settings->mode,
                tokensInput: 111,
                tokensOutput: 29,
            );
        }
    });

    $event = Event::factory()->active()->create([
        'moderation_mode' => 'manual',
    ]);

    EventMediaIntelligenceSetting::factory()->create([
        'event_id' => $event->id,
        'enabled' => true,
        'mode' => 'enrich_only',
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'caption' => null,
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

    app(EvaluateMediaPromptJob::class, ['eventMediaId' => $media->id])->handle();

    $media->refresh();

    expect($media->vlm_status)->toBe('completed')
        ->and($media->caption)->toBe('Entrada especial na festa.');

    $evaluation = EventMediaVlmEvaluation::query()
        ->where('event_media_id', $media->id)
        ->latest('id')
        ->first();

    expect($evaluation)->not->toBeNull()
        ->and($evaluation?->decision)->toBe('approve')
        ->and($evaluation?->short_caption)->toBe('Entrada especial na festa.')
        ->and($evaluation?->reply_text)->toBe('Memorias que fazem o coracao sorrir! 🎉📸');

    $run = MediaProcessingRun::query()
        ->where('event_media_id', $media->id)
        ->where('stage_key', 'vlm')
        ->latest('id')
        ->first();

    expect($run)->not->toBeNull()
        ->and($run?->status)->toBe('completed')
        ->and($run?->queue_name)->toBe('media-vlm')
        ->and($run?->decision_key)->toBe('approve');

    Queue::assertNotPushed(RunModerationJob::class);
});

it('does not overwrite a human caption when vlm returns a short caption', function () {
    Queue::fake();

    app()->bind(VisualReasoningProviderInterface::class, fn () => new class implements VisualReasoningProviderInterface
    {
        public function evaluate(EventMedia $media, EventMediaIntelligenceSetting $settings): VisualReasoningEvaluationResult
        {
            return VisualReasoningEvaluationResult::approve(
                reason: 'Imagem compativel com o evento.',
                shortCaption: 'Legenda sugerida pela IA.',
                tags: ['festa'],
                rawResponse: ['provider' => 'fake-vlm'],
                providerKey: 'fake-vlm',
                providerVersion: 'test-v1',
                modelKey: 'fake-vlm-model',
                modelSnapshot: 'fake-vlm-model@1',
                promptVersion: $settings->prompt_version,
                responseSchemaVersion: $settings->response_schema_version,
                modeApplied: $settings->mode,
                tokensInput: 87,
                tokensOutput: 19,
            );
        }
    });

    $event = Event::factory()->active()->create([
        'moderation_mode' => 'manual',
    ]);

    EventMediaIntelligenceSetting::factory()->create([
        'event_id' => $event->id,
        'enabled' => true,
        'mode' => 'enrich_only',
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'caption' => 'Legenda humana preservada.',
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

    app(EvaluateMediaPromptJob::class, ['eventMediaId' => $media->id])->handle();

    $media->refresh();
    $evaluation = EventMediaVlmEvaluation::query()
        ->where('event_media_id', $media->id)
        ->latest('id')
        ->first();

    expect($media->caption)->toBe('Legenda humana preservada.')
        ->and($evaluation)->not->toBeNull()
        ->and($evaluation?->short_caption)->toBe('Legenda sugerida pela IA.');
});

it('skips vlm generation when the ai reply burst guard blocks the sender in enrich_only mode', function () {
    Queue::fake();

    $counter = new class
    {
        public int $calls = 0;
    };

    app()->bind(VisualReasoningProviderInterface::class, fn () => new class($counter) implements VisualReasoningProviderInterface
    {
        public function __construct(private object $counter) {}

        public function evaluate(EventMedia $media, EventMediaIntelligenceSetting $settings): VisualReasoningEvaluationResult
        {
            $this->counter->calls++;

            return VisualReasoningEvaluationResult::approve(
                reason: 'Imagem compativel com o evento.',
                shortCaption: 'Legenda sugerida pela IA.',
                replyText: 'Memorias que fazem o coracao sorrir! 🎉📸',
                tags: ['festa'],
                rawResponse: ['provider' => 'fake-vlm'],
                providerKey: 'fake-vlm',
                providerVersion: 'test-v1',
                modelKey: 'fake-vlm-model',
                modelSnapshot: 'fake-vlm-model@1',
                promptVersion: $settings->prompt_version,
                responseSchemaVersion: $settings->response_schema_version,
                modeApplied: $settings->mode,
                tokensInput: 87,
                tokensOutput: 19,
            );
        }
    });

    \App\Modules\MediaIntelligence\Models\MediaIntelligenceGlobalSetting::query()->updateOrCreate(
        ['id' => 1],
        [
            'reply_text_prompt' => \App\Modules\MediaIntelligence\Models\MediaIntelligenceGlobalSetting::defaultReplyTextPrompt(),
            'reply_text_fixed_templates_json' => [],
            'reply_ai_rate_limit_enabled' => true,
            'reply_ai_rate_limit_max_messages' => 1,
            'reply_ai_rate_limit_window_minutes' => 10,
        ],
    );

    $event = Event::factory()->active()->create([
        'moderation_mode' => 'manual',
    ]);

    EventMediaIntelligenceSetting::factory()->create([
        'event_id' => $event->id,
        'enabled' => true,
        'mode' => 'enrich_only',
        'reply_text_mode' => 'ai',
        'reply_text_enabled' => true,
    ]);

    $firstInbound = \App\Modules\InboundMedia\Models\InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'whatsapp',
        'message_id' => 'msg-vlm-limit-1',
        'provider_message_id' => 'wamid-vlm-limit-1',
        'message_type' => 'image',
        'sender_phone' => '554899993333',
        'normalized_payload_json' => [
            '_event_context' => [
                'intake_source' => 'whatsapp_direct',
                'sender_external_id' => '554899993333',
                'chat_external_id' => '554899993333',
                'provider_message_id' => 'wamid-vlm-limit-1',
            ],
        ],
        'status' => 'processed',
        'received_at' => now(),
    ]);
    $secondInbound = \App\Modules\InboundMedia\Models\InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'whatsapp',
        'message_id' => 'msg-vlm-limit-2',
        'provider_message_id' => 'wamid-vlm-limit-2',
        'message_type' => 'image',
        'sender_phone' => '554899993333',
        'normalized_payload_json' => [
            '_event_context' => [
                'intake_source' => 'whatsapp_direct',
                'sender_external_id' => '554899993333',
                'chat_external_id' => '554899993333',
                'provider_message_id' => 'wamid-vlm-limit-2',
            ],
        ],
        'status' => 'processed',
        'received_at' => now(),
    ]);

    $firstMedia = EventMedia::factory()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $firstInbound->id,
        'vlm_status' => 'queued',
    ]);
    $secondMedia = EventMedia::factory()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $secondInbound->id,
        'vlm_status' => 'queued',
    ]);

    foreach ([$firstMedia, $secondMedia] as $media) {
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
    }

    app(EvaluateMediaPromptJob::class, ['eventMediaId' => $firstMedia->id])->handle();
    app(EvaluateMediaPromptJob::class, ['eventMediaId' => $secondMedia->id])->handle();

    $firstMedia->refresh();
    $secondMedia->refresh();

    expect($counter->calls)->toBe(1)
        ->and($firstMedia->vlm_status)->toBe('completed')
        ->and($secondMedia->vlm_status)->toBe('skipped');

    expect(EventMediaVlmEvaluation::query()->where('event_media_id', $firstMedia->id)->count())->toBe(1)
        ->and(EventMediaVlmEvaluation::query()->where('event_media_id', $secondMedia->id)->count())->toBe(0);

    $run = MediaProcessingRun::query()
        ->where('event_media_id', $secondMedia->id)
        ->where('stage_key', 'vlm')
        ->latest('id')
        ->first();

    expect($run)->not->toBeNull()
        ->and($run?->status)->toBe('completed')
        ->and(data_get($run?->result_json, 'reason'))->toBe('reply_ai_rate_limited');
});

it('keeps media approved when enrich_only vlm fails after moderation is already resolved', function () {
    Queue::fake();

    app()->bind(VisualReasoningProviderInterface::class, fn () => new class implements VisualReasoningProviderInterface
    {
        public function evaluate(EventMedia $media, EventMediaIntelligenceSetting $settings): VisualReasoningEvaluationResult
        {
            throw new RuntimeException('vlm timeout');
        }
    });

    $event = Event::factory()->active()->create([
        'moderation_mode' => 'none',
    ]);

    EventMediaIntelligenceSetting::factory()->create([
        'event_id' => $event->id,
        'enabled' => true,
        'mode' => 'enrich_only',
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'caption' => null,
        'moderation_status' => ModerationStatus::Pending->value,
        'safety_status' => 'skipped',
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

    app(RunModerationJob::class, ['eventMediaId' => $media->id])->handle();
    app(EvaluateMediaPromptJob::class, ['eventMediaId' => $media->id])->handle();

    $media->refresh();

    expect($media->moderation_status)->toBe(ModerationStatus::Approved)
        ->and($media->vlm_status)->toBe('failed')
        ->and($media->caption)->toBeNull();

    Queue::assertPushed(PublishMediaJob::class, fn (PublishMediaJob $job) => $job->eventMediaId === $media->id);
    Queue::assertNotPushed(RunModerationJob::class);
});

it('sends ai gate media back to pending when vlm fails', function () {
    Queue::fake();

    app()->bind(VisualReasoningProviderInterface::class, fn () => new class implements VisualReasoningProviderInterface
    {
        public function evaluate(EventMedia $media, EventMediaIntelligenceSetting $settings): VisualReasoningEvaluationResult
        {
            throw new RuntimeException('vlm timeout');
        }
    });

    $event = Event::factory()->active()->create([
        'moderation_mode' => 'ai',
    ]);

    EventMediaIntelligenceSetting::factory()->gate()->create([
        'event_id' => $event->id,
        'enabled' => true,
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'moderation_status' => ModerationStatus::Pending->value,
        'safety_status' => 'pass',
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

    app(EvaluateMediaPromptJob::class, ['eventMediaId' => $media->id])->handle();
    app(RunModerationJob::class, ['eventMediaId' => $media->id])->handle();

    $media->refresh();

    expect($media->vlm_status)->toBe('failed')
        ->and($media->moderation_status)->toBe(ModerationStatus::Pending);

    Queue::assertPushed(RunModerationJob::class, fn (RunModerationJob $job) => $job->eventMediaId === $media->id);
});
