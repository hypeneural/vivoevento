<?php

namespace App\Modules\MediaProcessing\Jobs;

use App\Modules\MediaProcessing\Actions\CleanupDeletedMediaArtifactsAction;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Services\MediaProcessingRunService;
use App\Modules\MediaProcessing\Services\PipelineFailureClassifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class CleanupDeletedMediaArtifactsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;
    public int $backoff = 10;

    public function __construct(
        public readonly int $eventMediaId,
    ) {
        $this->onQueue('media-process');
    }

    public function handle(): void
    {
        $media = EventMedia::withTrashed()
            ->with(['variants', 'faces', 'safetyEvaluations', 'vlmEvaluations'])
            ->find($this->eventMediaId);

        if (! $media) {
            return;
        }

        $runService = app(MediaProcessingRunService::class);
        $run = $runService->startStage($media, 'cleanup', [
            'provider_key' => 'eventovivo-core',
            'model_key' => 'cleanup-v1',
            'input_ref' => $media->originalStoragePath(),
            'idempotency_key' => "cleanup:{$media->id}",
            'queue_name' => 'media-process',
        ]);

        try {
            $result = app(CleanupDeletedMediaArtifactsAction::class)->execute($media);

            $runService->finishStage($run, [
                'decision_key' => 'cleaned',
                'result_json' => $result,
            ]);
        } catch (Throwable $exception) {
            $runService->failStage($run, $exception, [
                'decision_key' => 'failed',
                'failure_class' => app(PipelineFailureClassifier::class)->classify($exception),
            ]);

            throw $exception;
        }
    }

    public function tags(): array
    {
        return [
            'queue:media-process',
            'pipeline:cleanup',
            "event_media:{$this->eventMediaId}",
        ];
    }

    public function failed(Throwable $exception): void
    {
        Log::channel((string) config('observability.queue_log_channel', config('logging.default')))
            ->error('media_pipeline.job_failed', [
                'job' => static::class,
                'queue' => 'media-process',
                'stage' => 'cleanup',
                'event_media_id' => $this->eventMediaId,
                'exception_class' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
    }
}
