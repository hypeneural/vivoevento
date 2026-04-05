<?php

namespace App\Modules\MediaProcessing\Services;

use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Models\MediaProcessingRun;
use Throwable;

class MediaProcessingRunService
{
    public function startStage(
        EventMedia $media,
        string $stageKey,
        array $attributes = [],
    ): MediaProcessingRun {
        return MediaProcessingRun::query()->create([
            'event_media_id' => $media->id,
            'run_type' => $attributes['run_type'] ?? $stageKey,
            'stage_key' => $stageKey,
            'provider_key' => $attributes['provider_key'] ?? null,
            'provider_version' => $attributes['provider_version'] ?? null,
            'model_key' => $attributes['model_key'] ?? null,
            'model_snapshot' => $attributes['model_snapshot'] ?? null,
            'input_ref' => $attributes['input_ref'] ?? null,
            'idempotency_key' => $attributes['idempotency_key'] ?? null,
            'queue_name' => $attributes['queue_name'] ?? null,
            'worker_ref' => $attributes['worker_ref'] ?? $this->workerRef(),
            'status' => $attributes['status'] ?? 'processing',
            'attempts' => (int) ($attributes['attempts'] ?? 1),
            'started_at' => now(),
        ]);
    }

    public function finishStage(
        MediaProcessingRun $run,
        array $attributes = [],
    ): MediaProcessingRun {
        $run->forceFill([
            'status' => $attributes['status'] ?? 'completed',
            'provider_key' => $attributes['provider_key'] ?? $run->provider_key,
            'provider_version' => $attributes['provider_version'] ?? $run->provider_version,
            'model_key' => $attributes['model_key'] ?? $run->model_key,
            'model_snapshot' => $attributes['model_snapshot'] ?? $run->model_snapshot,
            'decision_key' => $attributes['decision_key'] ?? $run->decision_key,
            'queue_name' => $attributes['queue_name'] ?? $run->queue_name,
            'worker_ref' => $attributes['worker_ref'] ?? $run->worker_ref,
            'result_json' => $attributes['result_json'] ?? $run->result_json,
            'metrics_json' => $attributes['metrics_json'] ?? $run->metrics_json,
            'cost_units' => $attributes['cost_units'] ?? $run->cost_units,
            'error_message' => $attributes['error_message'] ?? $run->error_message,
            'failure_class' => $attributes['failure_class'] ?? $run->failure_class,
            'finished_at' => $attributes['finished_at'] ?? now(),
        ])->save();

        return $run->refresh();
    }

    public function failStage(
        MediaProcessingRun $run,
        Throwable $exception,
        array $attributes = [],
    ): MediaProcessingRun {
        $run->forceFill([
            'status' => $attributes['status'] ?? 'failed',
            'provider_key' => $attributes['provider_key'] ?? $run->provider_key,
            'provider_version' => $attributes['provider_version'] ?? $run->provider_version,
            'model_key' => $attributes['model_key'] ?? $run->model_key,
            'model_snapshot' => $attributes['model_snapshot'] ?? $run->model_snapshot,
            'decision_key' => $attributes['decision_key'] ?? $run->decision_key,
            'queue_name' => $attributes['queue_name'] ?? $run->queue_name,
            'worker_ref' => $attributes['worker_ref'] ?? $run->worker_ref ?? $this->workerRef(),
            'result_json' => $attributes['result_json'] ?? $run->result_json,
            'metrics_json' => $attributes['metrics_json'] ?? $run->metrics_json,
            'cost_units' => $attributes['cost_units'] ?? $run->cost_units,
            'error_message' => $attributes['error_message'] ?? $exception->getMessage(),
            'failure_class' => $attributes['failure_class'] ?? $run->failure_class ?? 'transient',
            'finished_at' => $attributes['finished_at'] ?? now(),
        ])->save();

        return $run->refresh();
    }

    private function workerRef(): string
    {
        return (string) (gethostname() ?: php_uname('n') ?: 'unknown-worker');
    }
}
