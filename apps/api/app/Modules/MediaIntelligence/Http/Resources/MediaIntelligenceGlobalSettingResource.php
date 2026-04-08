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
