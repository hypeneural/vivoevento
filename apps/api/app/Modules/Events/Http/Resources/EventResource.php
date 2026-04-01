<?php

namespace App\Modules\Events\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'organization_id' => $this->organization_id,
            'client_id' => $this->client_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'upload_slug' => $this->upload_slug,
            'event_type' => $this->event_type?->value,
            'status' => $this->status?->value,
            'visibility' => $this->visibility,
            'moderation_mode' => $this->moderation_mode,
            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'location_name' => $this->location_name,
            'description' => $this->description,
            'cover_image_path' => $this->cover_image_path,
            'logo_path' => $this->logo_path,
            'qr_code_path' => $this->qr_code_path,
            'primary_color' => $this->primary_color,
            'secondary_color' => $this->secondary_color,
            'public_url' => $this->public_url,
            'upload_url' => $this->upload_url,
            'upload_api_url' => $this->publicUploadApiUrl(),
            'retention_days' => $this->retention_days,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'client' => $this->whenLoaded('client'),
            'modules' => $this->whenLoaded('modules'),
            'channels' => $this->whenLoaded('channels'),
            'banners' => $this->whenLoaded('banners'),
            'team_members' => $this->whenLoaded('teamMembers'),
            'media_count' => $this->whenCounted('media'),
        ];
    }
}
