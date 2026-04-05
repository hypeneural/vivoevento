<?php

namespace App\Modules\MediaProcessing\Jobs;

use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Events\MediaPublished;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Services\MediaPipelineTelemetryService;
use App\Modules\MediaProcessing\Services\MediaProcessingRunService;
use App\Modules\MediaProcessing\Services\ModerationBroadcasterService;
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

class PublishMediaJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;
    public int $backoff = 5;
    public int $uniqueFor = 900;

    public function __construct(
        public readonly int $eventMediaId,
    ) {
        $this->onQueue('media-publish');
    }

    public function uniqueId(): string
    {
        return "media-publish:{$this->eventMediaId}";
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("media-publish:{$this->eventMediaId}"))
                ->releaseAfter(10)
                ->expireAfter($this->timeout + 60),
        ];
    }

    public function handle(): void
    {
        $media = EventMedia::query()->find($this->eventMediaId);
        $wasPublishedThisRun = false;

        if (! $media || $media->moderation_status !== ModerationStatus::Approved) {
            return;
        }

        $runService = app(MediaProcessingRunService::class);
        $run = $runService->startStage($media, 'publish', [
            'provider_key' => 'eventovivo-core',
            'model_key' => 'publish-v1',
            'input_ref' => $media->originalStoragePath(),
            'idempotency_key' => "publish:{$media->id}",
            'queue_name' => 'media-publish',
        ]);

        try {
            if ($media->publication_status !== PublicationStatus::Published) {
                $media->forceFill([
                    'publication_status' => PublicationStatus::Published,
                    'published_at' => $media->published_at ?? now(),
                    'last_pipeline_error_code' => null,
                    'last_pipeline_error_message' => null,
                ])->save();

                $media = $media->fresh();
                $wasPublishedThisRun = true;
            }

            $runService->finishStage($run, [
                'decision_key' => 'published',
                'result_json' => [
                    'publication_status' => $media->publication_status?->value,
                ],
                'metrics_json' => [
                    'published_at' => $media->published_at?->toIso8601String(),
                ],
            ]);
        } catch (Throwable $exception) {
            $media->forceFill([
                'last_pipeline_error_code' => 'publish_failed',
                'last_pipeline_error_message' => $exception->getMessage(),
            ])->save();

            $runService->failStage($run, $exception, [
                'decision_key' => 'failed',
                'failure_class' => app(PipelineFailureClassifier::class)->classify($exception),
            ]);

            throw $exception;
        }

        $media = $media->fresh(['event', 'variants', 'inboundMessage']);

        if ($wasPublishedThisRun) {
            app(MediaPipelineTelemetryService::class)->recordPublished($media);
        }

        event(MediaPublished::fromMedia($media));
        app(ModerationBroadcasterService::class)->broadcastUpdated($media);
    }

    public function tags(): array
    {
        return [
            'queue:media-publish',
            'pipeline:publish',
            "event_media:{$this->eventMediaId}",
        ];
    }

    public function failed(Throwable $exception): void
    {
        Log::channel((string) config('observability.queue_log_channel', config('logging.default')))
            ->error('media_pipeline.job_failed', [
                'job' => static::class,
                'queue' => 'media-publish',
                'stage' => 'publish',
                'event_media_id' => $this->eventMediaId,
                'exception_class' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
    }
}
