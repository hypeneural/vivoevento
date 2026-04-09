<?php

namespace App\Modules\MediaIntelligence\Http\Resources;

use App\Modules\MediaIntelligence\Services\MediaOperationalHistorySummaryService;
use App\Modules\MediaProcessing\Services\MediaAssetUrlService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaReplyEventHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $assets = app(MediaAssetUrlService::class);
        $operational = app(MediaOperationalHistorySummaryService::class)->summarize($this->resource);
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
            'effective_media_state' => $operational['effective_media_state'],
            'safety_decision' => $operational['safety_decision'],
            'context_decision' => $operational['context_decision'],
            'operator_decision' => $operational['operator_decision'],
            'publication_decision' => $operational['publication_decision'],
            'human_reason' => $operational['human_reason'],
            'reason' => $evaluation?->reason,
            'reason_code' => $evaluation?->reason_code,
            'matched_policies' => $evaluation?->matched_policies_json ?? [],
            'matched_exceptions' => $evaluation?->matched_exceptions_json ?? [],
            'input_scope_used' => $evaluation?->input_scope_used,
            'input_types_considered' => $evaluation?->input_types_considered_json ?? [],
            'confidence_band' => $evaluation?->confidence_band,
            'publish_eligibility' => $evaluation?->publish_eligibility,
            'policy_label' => $operational['policy_label'],
            'policy_inheritance_mode' => $operational['policy_inheritance_mode'],
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
            'text_context_summary' => $operational['text_context_summary'],
            'policy_snapshot' => $evaluation?->policy_snapshot_json ?? [],
            'policy_sources' => $evaluation?->policy_sources_json ?? [],
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
