<?php

namespace App\Modules\MediaProcessing\Jobs;

use App\Modules\FaceSearch\Jobs\IndexMediaFacesJob;
use App\Modules\MediaProcessing\Actions\FinalizeMediaDecisionAction;
use App\Modules\MediaProcessing\Events\MediaRejected;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Services\MediaEffectiveStateResolver;
use App\Modules\MediaProcessing\Services\MediaPipelineDegradationPolicy;
use App\Modules\MediaProcessing\Services\MediaProcessingRunService;
use App\Modules\MediaProcessing\Services\PipelineFailureClassifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunModerationJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;
    public int $backoff = 5;
    public int $uniqueFor = 900;

    public function __construct(
        public readonly int $eventMediaId,
    ) {
        $this->onQueue('media-audit');
    }

    public function uniqueId(): string
    {
        return "media-moderation:{$this->eventMediaId}";
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("media-moderation:{$this->eventMediaId}"))
                ->releaseAfter(10)
                ->expireAfter($this->timeout + 60),
        ];
    }

    public function handle(): void
    {
        $media = EventMedia::query()
            ->with('event')
            ->find($this->eventMediaId);
        $degradationPolicy = app(MediaPipelineDegradationPolicy::class);

        if (! $media || ! $media->event) {
            return;
        }

        $runService = app(MediaProcessingRunService::class);
        $run = $runService->startStage($media, 'moderation', [
            'provider_key' => 'eventovivo-core',
            'model_key' => 'decision-matrix-v1',
            'input_ref' => $media->originalStoragePath(),
            'idempotency_key' => "moderation:{$media->id}",
            'queue_name' => 'media-audit',
        ]);

        try {
            $previousModerationStatus = $media->moderation_status;
            $media = app(FinalizeMediaDecisionAction::class)->execute($media);
            $resolvedState = app(MediaEffectiveStateResolver::class)->resolve($media);

            $runService->finishStage($run, [
                'decision_key' => $media->moderation_status?->value,
                'result_json' => [
                    'moderation_status' => $media->moderation_status?->value,
                    'publication_status' => $media->publication_status?->value,
                    'safety_status' => $media->safety_status,
                    'vlm_status' => $media->vlm_status,
                    'effective_media_state' => $resolvedState['effective_media_state'],
                    'safety_decision' => $resolvedState['safety_decision'],
                    'context_decision' => $resolvedState['context_decision'],
                    'operator_decision' => $resolvedState['operator_decision'],
                    'publication_decision' => $resolvedState['publication_decision'],
                ],
                'metrics_json' => [
                    'event_moderation_mode' => $media->event?->moderation_mode?->value,
                    'safety_is_blocking' => $resolvedState['safety_is_blocking'],
                    'context_is_blocking' => $resolvedState['context_is_blocking'],
                ],
            ]);

            Log::channel((string) config('observability.queue_log_channel', config('logging.default')))
                ->info('media_pipeline.moderation_resolved', [
                    'event_media_id' => $media->id,
                    'moderation_status' => $media->moderation_status?->value,
                    'publication_status' => $media->publication_status?->value,
                    'effective_media_state' => $resolvedState['effective_media_state'],
                    'safety_status' => $media->safety_status,
                    'safety_decision' => $resolvedState['safety_decision'],
                    'safety_is_blocking' => $resolvedState['safety_is_blocking'],
                    'vlm_status' => $media->vlm_status,
                    'context_decision' => $resolvedState['context_decision'],
                    'context_is_blocking' => $resolvedState['context_is_blocking'],
                    'operator_decision' => $resolvedState['operator_decision'],
                    'publication_decision' => $resolvedState['publication_decision'],
                    'decision_source' => $media->decision_source?->value,
                ]);

            if ($previousModerationStatus !== ModerationStatus::Rejected && $media->moderation_status === ModerationStatus::Rejected) {
                event(MediaRejected::fromMedia($media));
            }
        } catch (Throwable $exception) {
            $media->forceFill([
                'last_pipeline_error_code' => 'moderation_failed',
                'last_pipeline_error_message' => $exception->getMessage(),
            ])->save();

            $runService->failStage($run, $exception, [
                'decision_key' => 'failed',
                'failure_class' => app(PipelineFailureClassifier::class)->classify($exception),
            ]);

            throw $exception;
        }

        if ($media->moderation_status === ModerationStatus::Approved) {
            PublishMediaJob::dispatch($media->id);
        }

        if ($media->face_index_status === 'queued') {
            if ($degradationPolicy->faceIndexEnabled()) {
                IndexMediaFacesJob::dispatch($media->id);
            } else {
                $media->forceFill([
                    'face_index_status' => 'skipped',
                ])->save();

                Log::channel((string) config('observability.queue_log_channel', config('logging.default')))
                    ->warning('media_pipeline.degraded', [
                        'stage' => 'face_index',
                        'event_media_id' => $media->id,
                        'queue' => 'face-index',
                        'reason' => 'ops_degradation_pause',
                    ]);
            }
        }
    }

    public function tags(): array
    {
        return [
            'queue:media-audit',
            'pipeline:moderation',
            "event_media:{$this->eventMediaId}",
        ];
    }

    public function failed(Throwable $exception): void
    {
        Log::channel((string) config('observability.queue_log_channel', config('logging.default')))
            ->error('media_pipeline.job_failed', [
                'job' => static::class,
                'queue' => 'media-audit',
                'stage' => 'moderation',
                'event_media_id' => $this->eventMediaId,
                'exception_class' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
    }
}
