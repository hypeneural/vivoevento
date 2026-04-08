<?php

namespace App\Modules\MediaIntelligence\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaReplyTestRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trace_id' => $this->trace_id,
            'user_id' => $this->user_id,
            'event_id' => $this->event_id,
            'preset_id' => $this->preset_id,
            'preset' => $this->whenLoaded('preset', fn () => (new MediaReplyPromptPresetResource($this->preset))->resolve()),
            'provider_key' => $this->provider_key,
            'model_key' => $this->model_key,
            'status' => $this->status,
            'prompt_template' => $this->prompt_template,
            'prompt_resolved' => $this->prompt_resolved,
            'prompt_variables' => $this->prompt_variables_json ?? [],
            'images' => $this->images_json ?? [],
            'request_payload' => $this->request_payload_json ?? [],
            'response_payload' => $this->response_payload_json ?? [],
            'response_text' => $this->response_text,
            'latency_ms' => $this->latency_ms,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
