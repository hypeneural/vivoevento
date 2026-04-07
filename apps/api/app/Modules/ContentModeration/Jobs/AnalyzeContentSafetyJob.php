<?php

namespace App\Modules\ContentModeration\Jobs;

use App\Modules\ContentModeration\Actions\EvaluateContentSafetyAction;
use App\Modules\MediaIntelligence\Jobs\EvaluateMediaPromptJob;
use App\Modules\MediaProcessing\Jobs\RunModerationJob;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Services\MediaPipelineDegradationPolicy;
use App\Modules\MediaProcessing\Services\MediaProcessingRunService;
use App\Modules\MediaProcessing\Services\ModerationBroadcasterService;
use App\Modules\MediaProcessing\Services\PipelineFailureClassifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\ThrottlesExceptionsWithRedis;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class AnalyzeContentSafetyJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;
    public int $timeout = 45;
    public int $backoff = 5;
    public int $uniqueFor = 900;

    public function __construct(
        public readonly int $eventMediaId,
    ) {
        $this->onQueue('media-safety');
    }

    public function uniqueId(): string
    {
        return "media-safety:{$this->eventMediaId}";
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("media-safety:{$this->eventMediaId}"))
                ->releaseAfter(10)
                ->expireAfter($this->timeout + 60),
            (new ThrottlesExceptionsWithRedis(3, 300))
                ->by('provider:content-moderation')
                ->backoff(1),
        ];
    }

    public function handle(): void
    {
        $media = EventMedia::query()
            ->with(['event.contentModerationSettings', 'event.mediaIntelligenceSettings', 'variants', 'inboundMessage'])
            ->find($this->eventMediaId);

        if (! $media || ! $media->event) {
            return;
        }

        $settings = $media->event->contentModerationSettings;
        $inputRef = $media->variants->firstWhere('variant_key', 'fast_preview')?->path
            ?: $media->originalStoragePath();
        $runService = app(MediaProcessingRunService::class);
        $degradationPolicy = app(MediaPipelineDegradationPolicy::class);
        $run = $runService->startStage($media, 'safety', [
            'provider_key' => $settings?->provider_key ?? 'noop',
            'model_key' => (string) ($settings?->provider_key === 'openai'
                ? config('content_moderation.providers.openai.model', 'omni-moderation-latest')
                : config('content_moderation.providers.noop.model', 'noop-safety-v1')),
            'input_ref' => $inputRef,
            'idempotency_key' => "safety:{$media->id}",
            'queue_name' => 'media-safety',
        ]);

        if ($forcedDecision = $degradationPolicy->forcedSafetyDecision()) {
            $media->forceFill([
                'safety_status' => $forcedDecision,
                'last_pipeline_error_code' => null,
                'last_pipeline_error_message' => null,
            ])->save();

            $runService->finishStage($run, [
                'provider_key' => 'ops-degradation',
                'provider_version' => 'runbook-v1',
                'model_key' => 'ops-degradation',
                'model_snapshot' => 'ops-degradation',
                'decision_key' => $forcedDecision,
                'result_json' => [
                    'degraded' => true,
                    'fallback' => $forcedDecision,
                ],
                'metrics_json' => [
                    'settings_enabled' => (bool) $settings?->enabled,
                    'degradation_mode' => $degradationPolicy->safetyMode(),
                ],
            ]);

            Log::channel((string) config('observability.queue_log_channel', config('logging.default')))
                ->warning('media_pipeline.degraded', [
                    'stage' => 'safety',
                    'event_media_id' => $media->id,
                    'queue' => 'media-safety',
                    'decision' => $forcedDecision,
                    'reason' => 'ops_degradation_fallback',
                ]);
        } else {
            try {
                $result = app(EvaluateContentSafetyAction::class)->execute($media);

                $media->forceFill([
                    'safety_status' => $result->safetyStatus(),
                    'last_pipeline_error_code' => null,
                    'last_pipeline_error_message' => null,
                ])->save();

                $runService->finishStage($run, [
                    'provider_key' => $result->providerKey,
                    'provider_version' => $result->providerVersion,
                    'model_key' => $result->modelKey,
                    'model_snapshot' => $result->modelSnapshot,
                    'decision_key' => $result->decision->value,
                    'result_json' => $result->toRunResult(),
                    'metrics_json' => [
                        'settings_enabled' => (bool) $settings?->enabled,
                        'review_required' => $result->reviewRequired,
                        'blocked' => $result->blocked,
                    ],
                ]);
            } catch (Throwable $exception) {
                $fallbackMode = $settings?->fallback_mode === 'block' ? 'block' : 'review';

                $media->forceFill([
                    'safety_status' => $fallbackMode === 'block' ? 'block' : 'failed',
                    'last_pipeline_error_code' => 'safety_failed',
                    'last_pipeline_error_message' => $exception->getMessage(),
                ])->save();

                $runService->failStage($run, $exception, [
                    'provider_key' => $settings?->provider_key ?? 'noop',
                    'model_key' => $run->model_key,
                    'decision_key' => $fallbackMode === 'block' ? 'block' : 'failed',
                    'result_json' => [
                        'fallback' => $fallbackMode,
                    ],
                    'failure_class' => app(PipelineFailureClassifier::class)->classify($exception),
                ]);

                Log::channel((string) config('observability.queue_log_channel', config('logging.default')))
                    ->warning('media_pipeline.safety_fallback', [
                        'event_media_id' => $media->id,
                        'fallback_mode' => $fallbackMode,
                        'exception_class' => $exception::class,
                        'message' => $exception->getMessage(),
                    ]);
            }
        }

        app(ModerationBroadcasterService::class)->broadcastUpdated(
            $media->fresh(['event', 'variants', 'inboundMessage']),
        );

        $freshMedia = $media->fresh(['event.mediaIntelligenceSettings', 'event.contentModerationSettings']);

        if (! $degradationPolicy->vlmEnabled() && in_array($freshMedia->vlm_status, [null, 'queued'], true)) {
            $freshMedia->forceFill([
                'vlm_status' => 'skipped',
            ])->save();

            $freshMedia = $freshMedia->fresh(['event.mediaIntelligenceSettings', 'event.contentModerationSettings']);

            Log::channel((string) config('observability.queue_log_channel', config('logging.default')))
                ->warning('media_pipeline.degraded', [
                    'stage' => 'vlm',
                    'event_media_id' => $freshMedia->id,
                    'queue' => 'media-vlm',
                    'reason' => 'ops_degradation_pause',
                ]);
        }

        $shouldRunVlm = $this->shouldRunVlm($freshMedia);
        $shouldWaitForVlmGate = $this->shouldWaitForVlmGate($freshMedia);

        if ($shouldRunVlm) {
            EvaluateMediaPromptJob::dispatch($freshMedia->id);
        }

        if (! $shouldWaitForVlmGate || ! $shouldRunVlm) {
            RunModerationJob::dispatch($freshMedia->id);
        }
    }

    public function tags(): array
    {
        return [
            'queue:media-safety',
            'pipeline:safety',
            "event_media:{$this->eventMediaId}",
        ];
    }

    public function failed(Throwable $exception): void
    {
        Log::channel((string) config('observability.queue_log_channel', config('logging.default')))
            ->error('media_pipeline.job_failed', [
                'job' => static::class,
                'queue' => 'media-safety',
                'stage' => 'safety',
                'event_media_id' => $this->eventMediaId,
                'exception_class' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
    }

    private function shouldRunVlm(EventMedia $media): bool
    {
        if (! app(MediaPipelineDegradationPolicy::class)->vlmEnabled()) {
            return false;
        }

        if ($media->media_type !== 'image') {
            return false;
        }

        $settings = $media->event?->mediaIntelligenceSettings;

        if (! $settings || ! $settings->enabled) {
            return false;
        }

        if ($media->event?->isContentModerationObserveOnly()) {
            return ! in_array($media->safety_status, [null, 'queued'], true);
        }

        return in_array($media->safety_status, ['pass', 'skipped'], true);
    }

    private function shouldWaitForVlmGate(EventMedia $media): bool
    {
        return $media->event?->isAiModeration()
            && app(MediaPipelineDegradationPolicy::class)->vlmEnabled()
            && (bool) ($media->event?->mediaIntelligenceSettings?->enabled ?? false)
            && ($media->event?->mediaIntelligenceSettings?->mode === 'gate')
            && $this->shouldRunVlm($media);
    }
}
