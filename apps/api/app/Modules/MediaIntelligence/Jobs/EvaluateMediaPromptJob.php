<?php

namespace App\Modules\MediaIntelligence\Jobs;

use App\Modules\MediaIntelligence\Actions\EvaluateMediaPromptAction;
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

class EvaluateMediaPromptJob implements ShouldBeUnique, ShouldQueue
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
        $this->onQueue('media-vlm');
    }

    public function uniqueId(): string
    {
        return "media-vlm:{$this->eventMediaId}";
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("media-vlm:{$this->eventMediaId}"))
                ->releaseAfter(10)
                ->expireAfter($this->timeout + 60),
            (new ThrottlesExceptionsWithRedis(3, 300))
                ->by('provider:media-vlm')
                ->backoff(1),
        ];
    }

    public function handle(): void
    {
        $media = EventMedia::query()
            ->with(['event.mediaIntelligenceSettings', 'variants', 'inboundMessage'])
            ->find($this->eventMediaId);

        if (! $media || ! $media->event) {
            return;
        }

        $settings = $media->event->mediaIntelligenceSettings;
        $inputRef = $media->variants->firstWhere('variant_key', 'fast_preview')?->path
            ?: $media->originalStoragePath();
        $runService = app(MediaProcessingRunService::class);
        $degradationPolicy = app(MediaPipelineDegradationPolicy::class);
        $run = $runService->startStage($media, 'vlm', [
            'provider_key' => $settings?->provider_key ?? 'noop',
            'model_key' => $settings?->model_key
                ?? (string) config('media_intelligence.providers.noop.model', 'noop-vlm-v1'),
            'input_ref' => $inputRef,
            'idempotency_key' => "vlm:{$media->id}",
            'queue_name' => 'media-vlm',
        ]);

        if (! $degradationPolicy->vlmEnabled()) {
            $media->forceFill([
                'vlm_status' => 'skipped',
                'last_pipeline_error_code' => null,
                'last_pipeline_error_message' => null,
            ])->save();

            $runService->finishStage($run, [
                'provider_key' => 'ops-degradation',
                'provider_version' => 'runbook-v1',
                'model_key' => 'ops-degradation',
                'model_snapshot' => 'ops-degradation',
                'decision_key' => 'skipped',
                'result_json' => [
                    'degraded' => true,
                    'fallback' => 'skipped',
                ],
                'metrics_json' => [
                    'mode_applied' => $settings?->mode,
                    'review_required' => false,
                ],
            ]);

            Log::channel((string) config('observability.queue_log_channel', config('logging.default')))
                ->warning('media_pipeline.degraded', [
                    'stage' => 'vlm',
                    'event_media_id' => $media->id,
                    'queue' => 'media-vlm',
                    'reason' => 'ops_degradation_pause',
                ]);
        } else {
            try {
                $result = app(EvaluateMediaPromptAction::class)->execute($media);

                $caption = $media->caption;

                if (($caption === null || trim($caption) === '') && $result->shortCaption) {
                    $caption = $result->shortCaption;
                }

                $media->forceFill([
                    'vlm_status' => $result->vlmStatus(),
                    'caption' => $caption,
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
                        'mode_applied' => $result->modeApplied,
                        'review_required' => $result->reviewRequired,
                        'tokens_input' => $result->tokensInput,
                        'tokens_output' => $result->tokensOutput,
                    ],
                ]);
            } catch (Throwable $exception) {
                $media->forceFill([
                    'vlm_status' => 'failed',
                    'last_pipeline_error_code' => 'vlm_failed',
                    'last_pipeline_error_message' => $exception->getMessage(),
                ])->save();

                $runService->failStage($run, $exception, [
                    'provider_key' => $settings?->provider_key ?? 'noop',
                    'model_key' => $settings?->model_key
                        ?? (string) config('media_intelligence.providers.noop.model', 'noop-vlm-v1'),
                    'decision_key' => 'failed',
                    'result_json' => [
                        'fallback' => $settings?->fallback_mode ?? 'review',
                    ],
                    'failure_class' => app(PipelineFailureClassifier::class)->classify($exception),
                ]);

                Log::channel((string) config('observability.queue_log_channel', config('logging.default')))
                    ->warning('media_pipeline.vlm_fallback', [
                        'event_media_id' => $media->id,
                        'fallback_mode' => $settings?->fallback_mode ?? 'review',
                        'exception_class' => $exception::class,
                        'message' => $exception->getMessage(),
                    ]);
            }
        }

        app(ModerationBroadcasterService::class)->broadcastUpdated(
            $media->fresh(['event', 'variants', 'inboundMessage']),
        );

        if ($this->shouldFinalizeModeration($media->fresh(['event.mediaIntelligenceSettings']))) {
            RunModerationJob::dispatch($media->id);
        }
    }

    public function tags(): array
    {
        return [
            'queue:media-vlm',
            'pipeline:vlm',
            "event_media:{$this->eventMediaId}",
        ];
    }

    public function failed(Throwable $exception): void
    {
        Log::channel((string) config('observability.queue_log_channel', config('logging.default')))
            ->error('media_pipeline.job_failed', [
                'job' => static::class,
                'queue' => 'media-vlm',
                'stage' => 'vlm',
                'event_media_id' => $this->eventMediaId,
                'exception_class' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
    }

    private function shouldFinalizeModeration(EventMedia $media): bool
    {
        return $media->event?->isAiModeration()
            && (bool) ($media->event?->mediaIntelligenceSettings?->enabled ?? false)
            && ($media->event?->mediaIntelligenceSettings?->mode === 'gate');
    }
}
