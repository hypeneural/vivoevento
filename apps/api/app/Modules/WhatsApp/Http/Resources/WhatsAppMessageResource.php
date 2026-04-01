<?php

namespace App\Modules\WhatsApp\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WhatsAppMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'instance_id' => $this->instance_id,
            'chat_id' => $this->chat_id,
            'direction' => $this->direction->value,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'provider_message_id' => $this->provider_message_id,
            'text_body' => $this->text_body,
            'media_url' => $this->media_url,
            'mime_type' => $this->mime_type,
            'sender_phone' => $this->sender_phone,
            'recipient_phone' => $this->recipient_phone,
            'sent_at' => $this->sent_at?->toISOString(),
            'received_at' => $this->received_at?->toISOString(),
            'failed_at' => $this->failed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'chat' => $this->whenLoaded('chat', fn () => [
                'id' => $this->chat->id,
                'type' => $this->chat->type->value,
                'display_name' => $this->chat->display_name,
                'is_group' => $this->chat->is_group,
            ]),
        ];
    }
}
