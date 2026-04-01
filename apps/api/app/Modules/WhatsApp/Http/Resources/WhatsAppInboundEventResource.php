<?php

namespace App\Modules\WhatsApp\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WhatsAppInboundEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'instance_id' => $this->instance_id,
            'provider_key' => $this->provider_key->value,
            'event_type' => $this->event_type,
            'provider_message_id' => $this->provider_message_id,
            'processing_status' => $this->processing_status->value,
            'error_message' => $this->error_message,
            'received_at' => $this->received_at?->toISOString(),
            'processed_at' => $this->processed_at?->toISOString(),
        ];
    }
}
