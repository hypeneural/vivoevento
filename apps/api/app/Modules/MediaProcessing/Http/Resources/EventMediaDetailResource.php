<?php

namespace App\Modules\MediaProcessing\Http\Resources;

use App\Modules\MediaProcessing\Services\MediaAssetUrlService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventMediaDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $assets = app(MediaAssetUrlService::class);

        return array_merge(
            (new EventMediaResource($this->resource))->toArray($request),
            [
                'title' => $this->title,
                'source_label' => $this->source_label,
                'original_filename' => $this->displayFilename(),
                'client_filename' => $this->client_filename,
                'perceptual_hash' => $this->perceptual_hash,
                'duplicate_group_key' => $this->duplicate_group_key,
                'is_duplicate_candidate' => $this->duplicate_group_key !== null,
                'mime_type' => $this->mime_type,
                'size_bytes' => $this->size_bytes,
                'duration_seconds' => $this->duration_seconds,
                'preview_url' => $assets->preview($this->resource),
                'original_url' => $assets->original($this->resource),
                'variants' => $this->whenLoaded('variants', function () use ($assets) {
                    return $this->variants->map(fn ($variant) => [
                        'id' => $variant->id,
                        'variant_key' => $variant->variant_key,
                        'disk' => $variant->disk,
                        'path' => $variant->path,
                        'url' => $assets->toPublicUrl($variant->path, $variant->disk ?: 'public'),
                        'mime_type' => $variant->mime_type,
                        'width' => $variant->width,
                        'height' => $variant->height,
                        'size_bytes' => $variant->size_bytes,
                    ])->values()->all();
                }, []),
                'processing_runs' => $this->whenLoaded('processingRuns', function () {
                    return $this->processingRuns->map(fn ($run) => [
                        'id' => $run->id,
                        'run_type' => $run->run_type,
                        'stage_key' => $run->stage_key,
                        'provider_key' => $run->provider_key,
                        'provider_version' => $run->provider_version,
                        'model_key' => $run->model_key,
                        'model_snapshot' => $run->model_snapshot,
                        'input_ref' => $run->input_ref,
                        'decision_key' => $run->decision_key,
                        'queue_name' => $run->queue_name,
                        'worker_ref' => $run->worker_ref,
                        'result_json' => $run->result_json,
                        'metrics_json' => $run->metrics_json,
                        'cost_units' => $run->cost_units,
                        'idempotency_key' => $run->idempotency_key,
                        'status' => $run->status,
                        'attempts' => $run->attempts,
                        'error_message' => $run->error_message,
                        'failure_class' => $run->failure_class,
                        'started_at' => $run->started_at?->toIso8601String(),
                        'finished_at' => $run->finished_at?->toIso8601String(),
                    ])->values()->all();
                }, []),
                'decision_override' => $this->decision_source ? [
                    'source' => $this->decision_source?->value,
                    'overridden_at' => $this->decision_overridden_at?->toIso8601String(),
                    'overridden_by_user_id' => $this->decision_overridden_by_user_id,
                    'overridden_by' => $this->whenLoaded('decisionOverriddenBy', fn () => $this->decisionOverriddenBy ? [
                        'id' => $this->decisionOverriddenBy->id,
                        'name' => $this->decisionOverriddenBy->name,
                        'email' => $this->decisionOverriddenBy->email,
                    ] : null),
                    'reason' => $this->decision_override_reason,
                ] : null,
                'latest_safety_evaluation' => $this->whenLoaded('latestSafetyEvaluation', fn () => $this->latestSafetyEvaluation ? [
                    'id' => $this->latestSafetyEvaluation->id,
                    'provider_key' => $this->latestSafetyEvaluation->provider_key,
                    'provider_version' => $this->latestSafetyEvaluation->provider_version,
                    'model_key' => $this->latestSafetyEvaluation->model_key,
                    'model_snapshot' => $this->latestSafetyEvaluation->model_snapshot,
                    'threshold_version' => $this->latestSafetyEvaluation->threshold_version,
                    'decision' => $this->latestSafetyEvaluation->decision,
                    'blocked' => (bool) $this->latestSafetyEvaluation->blocked,
                    'review_required' => (bool) $this->latestSafetyEvaluation->review_required,
                    'category_scores' => $this->latestSafetyEvaluation->category_scores_json ?? [],
                    'reason_codes' => $this->latestSafetyEvaluation->reason_codes_json ?? [],
                    'completed_at' => $this->latestSafetyEvaluation->completed_at?->toIso8601String(),
                ] : null),
                'latest_vlm_evaluation' => $this->whenLoaded('latestVlmEvaluation', fn () => $this->latestVlmEvaluation ? [
                    'id' => $this->latestVlmEvaluation->id,
                    'provider_key' => $this->latestVlmEvaluation->provider_key,
                    'provider_version' => $this->latestVlmEvaluation->provider_version,
                    'model_key' => $this->latestVlmEvaluation->model_key,
                    'model_snapshot' => $this->latestVlmEvaluation->model_snapshot,
                    'prompt_version' => $this->latestVlmEvaluation->prompt_version,
                    'response_schema_version' => $this->latestVlmEvaluation->response_schema_version,
                    'mode_applied' => $this->latestVlmEvaluation->mode_applied,
                    'decision' => $this->latestVlmEvaluation->decision,
                    'review_required' => (bool) $this->latestVlmEvaluation->review_required,
                    'reason' => $this->latestVlmEvaluation->reason,
                    'short_caption' => $this->latestVlmEvaluation->short_caption,
                    'tags' => $this->latestVlmEvaluation->tags_json ?? [],
                    'tokens_input' => $this->latestVlmEvaluation->tokens_input,
                    'tokens_output' => $this->latestVlmEvaluation->tokens_output,
                    'completed_at' => $this->latestVlmEvaluation->completed_at?->toIso8601String(),
                ] : null),
                'indexed_faces_count' => $this->whenCounted('faces'),
            ],
        );
    }
}
