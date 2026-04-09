<?php

namespace App\Modules\FaceSearch\Jobs;

use App\Modules\FaceSearch\Actions\IndexMediaFacesAction;
use App\Modules\FaceSearch\Jobs\SyncAwsUserVectorJob;
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

class IndexMediaFacesJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;
    public int $backoff = 10;
    public int $uniqueFor = 1800;

    public function __construct(
        public readonly int $eventMediaId,
    ) {
        $this->onQueue('face-index');
    }

    public function uniqueId(): string
    {
        return "face-index:{$this->eventMediaId}";
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("face-index:{$this->eventMediaId}"))
                ->releaseAfter(15)
                ->expireAfter($this->timeout + 60),
            (new ThrottlesExceptionsWithRedis(3, 300))
                ->by('provider:face-index')
                ->backoff(1),
        ];
    }

    public function handle(): void
    {
        $media = EventMedia::query()
            ->with(['event.faceSearchSettings', 'variants', 'faces'])
            ->find($this->eventMediaId);

        if (! $media || ! $media->event) {
            return;
        }

        $settings = $media->event->faceSearchSettings;
        $runService = app(MediaProcessingRunService::class);
        $degradationPolicy = app(MediaPipelineDegradationPolicy::class);
        $queueName = $this->resolvedQueueName();

        $media->forceFill([
            'face_index_status' => 'processing',
            'last_pipeline_error_code' => null,
            'last_pipeline_error_message' => null,
        ])->save();

        $run = $runService->startStage($media, 'face_index', [
            'provider_key' => $settings?->provider_key ?? 'noop',
            'model_key' => $settings?->embedding_model_key ?? (string) config('face_search.default_embedding_model', 'face-embedding-foundation-v1'),
            'input_ref' => $media->variants->firstWhere('variant_key', 'gallery')?->path ?: $media->originalStoragePath(),
            'idempotency_key' => "face_index:{$media->id}",
            'queue_name' => $queueName,
        ]);

        try {
            if (! $degradationPolicy->faceIndexEnabled()) {
                $media->forceFill([
                    'face_index_status' => 'skipped',
                ])->save();

                $runService->finishStage($run, [
                    'provider_key' => 'ops-degradation',
                    'provider_version' => 'runbook-v1',
                    'model_key' => 'ops-degradation',
                    'model_snapshot' => 'ops-degradation',
                    'decision_key' => 'skipped',
                    'result_json' => [
                        'status' => 'skipped',
                        'faces_detected' => 0,
                        'faces_indexed' => 0,
                        'skipped_reason' => 'ops_degradation_pause',
                        'degraded' => true,
                    ],
                    'metrics_json' => [
                        'faces_detected' => 0,
                        'faces_indexed' => 0,
                    ],
                ]);

                Log::channel((string) config('observability.queue_log_channel', config('logging.default')))
                    ->warning('media_pipeline.degraded', [
                        'stage' => 'face_index',
                        'event_media_id' => $media->id,
                        'queue' => $queueName,
                        'reason' => 'ops_degradation_pause',
                    ]);

                return;
            }

            $result = app(IndexMediaFacesAction::class)->execute($media);

            $media->forceFill([
                'face_index_status' => $result['status'],
            ])->save();

            if (
                $result['status'] === 'indexed'
                && $settings?->enabled
                && $settings->recognition_enabled
                && $settings->search_backend_key === 'aws_rekognition'
            ) {
                SyncAwsUserVectorJob::dispatch($media->event_id);
            }

            $runService->finishStage($run, [
                'provider_key' => $settings?->provider_key ?? 'noop',
                'provider_version' => (string) config('face_search.providers.noop.provider_version', 'foundation-v1'),
                'model_key' => $settings?->embedding_model_key ?? (string) config('face_search.default_embedding_model', 'face-embedding-foundation-v1'),
                'model_snapshot' => (string) config('face_search.providers.noop.model_snapshot', 'noop-face-v1'),
                'decision_key' => $result['status'],
                'result_json' => $result,
                'metrics_json' => [
                    'faces_detected' => $result['faces_detected'],
                    'faces_indexed' => $result['faces_indexed'],
                ],
            ]);
        } catch (Throwable $exception) {
            $media->forceFill([
                'face_index_status' => 'failed',
                'last_pipeline_error_code' => 'face_index_failed',
                'last_pipeline_error_message' => $exception->getMessage(),
            ])->save();

            $runService->failStage($run, $exception, [
                'provider_key' => $settings?->provider_key ?? 'noop',
                'model_key' => $settings?->embedding_model_key ?? (string) config('face_search.default_embedding_model', 'face-embedding-foundation-v1'),
                'decision_key' => 'failed',
                'failure_class' => app(PipelineFailureClassifier::class)->classify($exception),
            ]);

            throw $exception;
        } finally {
            app(ModerationBroadcasterService::class)->broadcastUpdated(
                $media->fresh(['event', 'variants', 'inboundMessage']),
            );
        }
    }

    public function tags(): array
    {
        $queueName = $this->resolvedQueueName();

        return [
            "queue:{$queueName}",
            'pipeline:face_index',
            "event_media:{$this->eventMediaId}",
        ];
    }

    public function failed(Throwable $exception): void
    {
        Log::channel((string) config('observability.queue_log_channel', config('logging.default')))
            ->error('media_pipeline.job_failed', [
                'job' => static::class,
                'queue' => $this->resolvedQueueName(),
                'stage' => 'face_index',
                'event_media_id' => $this->eventMediaId,
                'exception_class' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
    }

    private function resolvedQueueName(): string
    {
        return is_string($this->queue) && $this->queue !== ''
            ? $this->queue
            : 'face-index';
    }
}
