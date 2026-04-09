<?php

namespace App\Modules\MediaIntelligence\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventMediaIntelligenceSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'enabled' => (bool) $this->enabled,
            'provider_key' => $this->provider_key,
            'model_key' => $this->model_key,
            'mode' => $this->mode,
            'prompt_version' => $this->prompt_version,
            'approval_prompt' => $this->contextualFreeformInstruction(),
            'freeform_instruction' => $this->contextualFreeformInstruction(),
            'caption_style_prompt' => $this->caption_style_prompt,
            'response_schema_version' => $this->response_schema_version,
            'timeout_ms' => $this->timeout_ms,
            'fallback_mode' => $this->fallback_mode,
            'context_scope' => $this->context_scope,
            'reply_scope' => $this->reply_scope,
            'normalized_text_context_mode' => $this->normalized_text_context_mode,
            'contextual_policy_preset_key' => $this->contextual_policy_preset_key,
            'policy_version' => $this->policy_version,
            'allow_alcohol' => $this->allow_alcohol,
            'allow_tobacco' => $this->allow_tobacco,
            'required_people_context' => $this->required_people_context,
            'blocked_terms' => $this->blocked_terms_json ?? [],
            'allowed_exceptions' => $this->allowed_exceptions_json ?? [],
            'require_json_output' => (bool) $this->require_json_output,
            'reply_text_mode' => $this->resolvedReplyTextMode(),
            'reply_text_enabled' => $this->automaticReplyEnabled(),
            'reply_prompt_override' => $this->reply_prompt_override,
            'reply_fixed_templates' => $this->reply_fixed_templates_json ?? [],
            'reply_prompt_preset_id' => $this->reply_prompt_preset_id,
            'reply_prompt_preset' => $this->whenLoaded('replyPromptPreset', fn () => (new MediaReplyPromptPresetResource($this->replyPromptPreset))->resolve()),
            'inherits_global' => (bool) ($this->inherits_global ?? false),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
