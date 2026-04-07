<?php

namespace App\Modules\Organizations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class OrganizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'type' => $this->type?->value,
            'legal_name' => $this->legal_name,
            'trade_name' => $this->trade_name,
            'document_number' => $this->when($request->user()?->can('organizations.update'), $this->document_number),
            'slug' => $this->slug,
            'email' => $this->email,
            'billing_email' => $this->when($request->user()?->can('billing.view'), $this->billing_email),
            'phone' => $this->phone,
            'logo_path' => $this->logo_path,
            'logo_url' => $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null,
            'primary_color' => $this->primary_color,
            'secondary_color' => $this->secondary_color,
            'subdomain' => $this->subdomain,
            'custom_domain' => $this->custom_domain,
            'timezone' => $this->timezone,
            'status' => $this->status?->value,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'members' => $this->whenLoaded('members'),
            'clients_count' => $this->whenCounted('clients'),
            'events_count' => $this->whenCounted('events'),
        ];
    }
}
