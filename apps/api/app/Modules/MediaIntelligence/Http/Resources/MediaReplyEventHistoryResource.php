<?php

namespace App\Modules\MediaIntelligence\Http\Resources;

use App\Modules\MediaProcessing\Services\MediaAssetUrlService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaReplyEventHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $assets = app(MediaAssetUrlService::class);
        $evaluation = $this->latestVlmEvaluation;
        $run = $this->latestVlmRun;
        $inbound = $this->inboundMessage;
        $event = $this->event;
        $promptContext = $evaluation?->prompt_context_json ?? [];

        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'event_title' => $event?->title,
            'event_media_id' => $this->id,
            'inbound_message_id' => $this->inbound_message_id,
            'provider_message_id' => $inbound?->message_id,
            'trace_id' => $inbound?->trace_id,
            'source_type' => $this->source_type,
            'source_label' => $this->source_label,
            'sender_name' => $inbound?->sender_name,
            'sender_phone' => $inbound?->sender_phone,
            'sender_external_id' => $inbound?->sender_external_id,
            'message_type' => $inbound?->message_type,
            'media_type' => $this->media_type,
            'mime_type' => $this->mime_type,
            'preview_url' => $assets->preview($this->resource),
            'provider_key' => $evaluation?->provider_key ?? $run?->provider_key,
            'model_key' => $evaluation?->model_key ?? $run?->model_key,
            'status' => $this->vlm_status,
            'decision' => $evaluation?->decision ?? $run?->decision_key,
            'prompt_template' => $evaluation?->prompt_context_json['template'] ?? null,
            'prompt_resolved' => $evaluation?->prompt_context_json['resolved'] ?? null,
            'prompt_variables' => $evaluation?->prompt_context_json['variables'] ?? [],
            'preset_name' => $promptContext['preset_name'] ?? null,
            'preset_id' => $promptContext['preset_id'] ?? null,
            'prompt_instruction_source' => $promptContext['instruction_source'] ?? null,
            'prompt_preset_source' => $promptContext['preset_source'] ?? null,
            'normalized_text_context' => $evaluation?->normalized_text_context ?? ($promptContext['normalized_text_context'] ?? null),
            'normalized_text_context_mode' => $evaluation?->normalized_text_context_mode ?? ($promptContext['normalized_text_context_mode'] ?? null),
            'context_scope' => $promptContext['context_scope'] ?? null,
            'reply_scope' => $promptContext['reply_scope'] ?? null,
            'reply_text' => $evaluation?->reply_text,
            'short_caption' => $evaluation?->short_caption,
            'tags' => $evaluation?->tags_json ?? [],
            'request_payload' => $evaluation?->request_payload_json ?? [],
            'response_payload' => $evaluation?->raw_response_json ?? [],
            'error_message' => $run?->error_message ?? $this->last_pipeline_error_message,
            'run_status' => $run?->status,
            'run_started_at' => $run?->started_at?->toIso8601String(),
            'run_finished_at' => $run?->finished_at?->toIso8601String(),
            'completed_at' => $evaluation?->completed_at?->toIso8601String() ?? $run?->finished_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
