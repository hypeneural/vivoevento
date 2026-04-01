<?php

namespace App\Modules\WhatsApp\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WhatsAppInstanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'organization_id' => $this->organization_id,
            'provider' => [
                'id' => $this->provider?->id,
                'key' => $this->provider_key,
                'name' => $this->provider?->name,
            ],
            'name' => $this->name,
            'external_instance_id' => $this->external_instance_id,
            'phone_number' => $this->phone_number,
            'status' => $this->status->value,
            'connected_at' => $this->connected_at?->toISOString(),
            'disconnected_at' => $this->disconnected_at?->toISOString(),
            'last_status_sync_at' => $this->last_status_sync_at?->toISOString(),
            'settings' => $this->settings_json,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
