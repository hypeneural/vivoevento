<?php

namespace App\Modules\WhatsApp\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WhatsAppDispatchLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'instance_id' => $this->instance_id,
            'message_id' => $this->message_id,
            'provider_key' => $this->provider_key->value,
            'endpoint_used' => $this->endpoint_used,
            'http_status' => $this->http_status,
            'success' => $this->success,
            'error_message' => $this->error_message,
            'duration_ms' => $this->duration_ms,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
