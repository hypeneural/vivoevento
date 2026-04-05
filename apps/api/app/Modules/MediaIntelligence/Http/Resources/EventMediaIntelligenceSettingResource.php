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
            'approval_prompt' => $this->approval_prompt,
            'caption_style_prompt' => $this->caption_style_prompt,
            'response_schema_version' => $this->response_schema_version,
            'timeout_ms' => $this->timeout_ms,
            'fallback_mode' => $this->fallback_mode,
            'require_json_output' => (bool) $this->require_json_output,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
