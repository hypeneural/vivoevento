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
            'provider_key' => $this->providerKeyValue(),
            'provider' => [
                'id' => $this->provider?->id,
                'key' => $this->providerKeyValue(),
                'name' => $this->provider?->name ?? $this->providerLabel(),
                'label' => $this->providerLabel(),
            ],
            'name' => $this->name,
            'instance_name' => $this->instance_name,
            'external_instance_id' => $this->external_instance_id,
            'phone_number' => $this->phone_number,
            'formatted_phone' => $this->formattedPhone(),
            'is_active' => (bool) $this->is_active,
            'is_default' => (bool) $this->is_default,
            'status' => $this->normalizedStatus()->value,
            'raw_status' => $this->status?->value,
            'connected_at' => $this->connected_at?->toISOString(),
            'disconnected_at' => $this->disconnected_at?->toISOString(),
            'last_status_sync_at' => $this->last_status_sync_at?->toISOString(),
            'last_health_check_at' => $this->last_health_check_at?->toISOString(),
            'last_health_status' => $this->last_health_status,
            'last_error' => $this->last_error,
            'notes' => $this->notes,
            'settings' => $this->settings_json ?? [],
            'provider_config' => $this->maskedProviderConfig(),
            'provider_meta' => $this->provider_meta_json ?? [],
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
