<?php

namespace App\Modules\MediaProcessing\Jobs;

use App\Modules\MediaProcessing\Actions\SyncEventMediaDuplicateGroupAction;
use App\Modules\MediaProcessing\Enums\MediaProcessingStatus;
use App\Modules\MediaProcessing\Events\MediaVariantsGenerated;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Services\MediaProcessingRunService;
use App\Modules\MediaProcessing\Services\MediaVariantGeneratorService;
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

class GenerateMediaVariantsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;
    public int $backoff = 10;
    public int $uniqueFor = 1800;

    public function __construct(
        public readonly int $eventMediaId,
    ) {
        $this->onQueue('media-fast');
    }

    public function uniqueId(): string
    {
        return "media-variants:{$this->eventMediaId}";
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("media-variants:{$this->eventMediaId}"))
                ->releaseAfter(10)
                ->expireAfter($this->timeout + 60),
        ];
    }

    public function handle(): void
    {
        $media = EventMedia::query()
            ->with('variants')
            ->find($this->eventMediaId);

        if (! $media) {
            return;
        }

        $runService = app(MediaProcessingRunService::class);
        $variantGenerator = app(MediaVariantGeneratorService::class);
        $duplicateGrouping = app(SyncEventMediaDuplicateGroupAction::class);
        $run = $runService->startStage($media, 'variants', [
            'provider_key' => 'intervention-image',
            'model_key' => 'intervention-image-v4',
            'input_ref' => $media->originalStoragePath(),
            'idempotency_key' => "variants:{$media->id}",
            'queue_name' => 'media-fast',
        ]);

        try {
            $summary = $variantGenerator->generate($media);
            $fingerprintSummary = [
                'status' => $summary['perceptual_hash'] ? 'pending' : 'skipped',
                'perceptual_hash' => $summary['perceptual_hash'],
                'duplicate_group_key' => null,
                'matched_media_id' => null,
                'hamming_distance' => null,
                'threshold' => null,
                'match_type' => null,
            ];

            if ($summary['perceptual_hash']) {
                try {
                    $fingerprintSummary = $duplicateGrouping->execute($media, $summary['perceptual_hash']);
                } catch (Throwable $fingerprintException) {
                    Log::warning('media_pipeline.fingerprint_failed', [
                        'event_media_id' => $media->id,
                        'exception_class' => $fingerprintException::class,
                        'message' => $fingerprintException->getMessage(),
                    ]);

                    $fingerprintSummary = [
                        'status' => 'failed',
                        'perceptual_hash' => $summary['perceptual_hash'],
                        'duplicate_group_key' => null,
                        'matched_media_id' => null,
                        'hamming_distance' => null,
                        'threshold' => null,
                        'match_type' => null,
                    ];
                }
            }

            $media->forceFill([
                'processing_status' => MediaProcessingStatus::Processed,
                'pipeline_version' => $media->pipeline_version ?: 'media_ai_foundation_v1',
                'last_pipeline_error_code' => null,
                'last_pipeline_error_message' => null,
            ])->save();

            $runService->finishStage($run, [
                'decision_key' => 'generated',
                'result_json' => [
                    'variant_keys' => $summary['variant_keys'],
                    'generated_count' => $summary['generated_count'],
                    'perceptual_hash' => $fingerprintSummary['perceptual_hash'],
                    'duplicate_group_key' => $fingerprintSummary['duplicate_group_key'],
                    'matched_media_id' => $fingerprintSummary['matched_media_id'],
                    'fingerprint_status' => $fingerprintSummary['status'],
                    'match_type' => $fingerprintSummary['match_type'],
                ],
                'metrics_json' => [
                    'source_width' => $summary['source_width'],
                    'source_height' => $summary['source_height'],
                    'fingerprint_hamming_distance' => $fingerprintSummary['hamming_distance'],
                    'fingerprint_threshold' => $fingerprintSummary['threshold'],
                ],
            ]);
        } catch (Throwable $exception) {
            $media->forceFill([
                'processing_status' => MediaProcessingStatus::Failed,
                'last_pipeline_error_code' => 'variants_failed',
                'last_pipeline_error_message' => $exception->getMessage(),
            ])->save();

            $runService->failStage($run, $exception, [
                'decision_key' => 'failed',
                'failure_class' => app(PipelineFailureClassifier::class)->classify($exception),
            ]);

            throw $exception;
        }

        $media = $media->fresh(['variants']);

        event(MediaVariantsGenerated::fromMedia($media));
        app(ModerationBroadcasterService::class)->broadcastUpdated(
            $media->fresh(['event', 'variants', 'inboundMessage']),
        );
    }

    public function tags(): array
    {
        return [
            'queue:media-fast',
            'pipeline:variants',
            "event_media:{$this->eventMediaId}",
        ];
    }

    public function failed(Throwable $exception): void
    {
        Log::channel((string) config('observability.queue_log_channel', config('logging.default')))
            ->error('media_pipeline.job_failed', [
                'job' => static::class,
                'queue' => 'media-fast',
                'stage' => 'variants',
                'event_media_id' => $this->eventMediaId,
                'exception_class' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
    }
}
