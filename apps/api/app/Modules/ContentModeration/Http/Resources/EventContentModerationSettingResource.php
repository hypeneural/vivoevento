<?php

namespace App\Modules\ContentModeration\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventContentModerationSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'enabled' => (bool) $this->enabled,
            'provider_key' => $this->provider_key,
            'mode' => $this->mode,
            'threshold_version' => $this->threshold_version,
            'hard_block_thresholds' => $this->hard_block_thresholds_json ?? [],
            'review_thresholds' => $this->review_thresholds_json ?? [],
            'fallback_mode' => $this->fallback_mode,
            'analysis_scope' => $this->analysis_scope,
            'objective_safety_scope' => $this->analysis_scope,
            'normalized_text_context_mode' => $this->normalized_text_context_mode,
            'inherits_global' => (bool) ($this->inherits_global ?? false),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
