<?php

namespace App\Modules\WhatsApp\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WhatsAppGroupBindingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'event_id' => $this->event_id,
            'instance_id' => $this->instance_id,
            'group_external_id' => $this->group_external_id,
            'group_name' => $this->group_name,
            'binding_type' => $this->binding_type->value,
            'is_active' => $this->is_active,
            'metadata' => $this->metadata_json,
            'instance' => $this->whenLoaded('instance', fn () => [
                'id' => $this->instance->id,
                'name' => $this->instance->name,
                'status' => $this->instance->status->value,
            ]),
            'event' => $this->whenLoaded('event', fn () => [
                'id' => $this->event->id,
                'title' => $this->event->title,
                'slug' => $this->event->slug,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
