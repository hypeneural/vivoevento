<?php

namespace App\Modules\MediaIntelligence\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaIntelligenceGlobalSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'enabled' => (bool) ($this->enabled ?? false),
            'provider_key' => $this->provider_key,
            'model_key' => $this->model_key,
            'mode' => $this->mode,
            'prompt_version' => $this->prompt_version,
            'response_schema_version' => $this->response_schema_version,
            'timeout_ms' => $this->timeout_ms,
            'fallback_mode' => $this->fallback_mode,
            'context_scope' => $this->context_scope,
            'reply_scope' => $this->reply_scope,
            'normalized_text_context_mode' => $this->normalized_text_context_mode,
            'require_json_output' => (bool) ($this->require_json_output ?? true),
            'contextual_policy_preset_key' => $this->contextual_policy_preset_key,
            'policy_version' => $this->policy_version,
            'allow_alcohol' => (bool) ($this->allow_alcohol ?? false),
            'allow_tobacco' => (bool) ($this->allow_tobacco ?? false),
            'required_people_context' => $this->required_people_context,
            'blocked_terms' => $this->blocked_terms_json ?? [],
            'allowed_exceptions' => $this->allowed_exceptions_json ?? [],
            'freeform_instruction' => $this->freeform_instruction,
            'reply_text_prompt' => $this->reply_text_prompt,
            'reply_text_fixed_templates' => $this->reply_text_fixed_templates_json ?? [],
            'reply_prompt_preset_id' => $this->reply_prompt_preset_id,
            'reply_prompt_preset' => $this->whenLoaded('replyPromptPreset', fn () => (new MediaReplyPromptPresetResource($this->replyPromptPreset))->resolve()),
            'reply_ai_rate_limit_enabled' => (bool) ($this->reply_ai_rate_limit_enabled ?? false),
            'reply_ai_rate_limit_max_messages' => (int) ($this->reply_ai_rate_limit_max_messages ?? 10),
            'reply_ai_rate_limit_window_minutes' => (int) ($this->reply_ai_rate_limit_window_minutes ?? 10),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
