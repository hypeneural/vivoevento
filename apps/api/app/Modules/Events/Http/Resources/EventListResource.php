<?php

namespace App\Modules\Events\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class EventListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $organizationName = $this->organization?->trade_name
            ?? $this->organization?->legal_name
            ?? $this->organization?->name;

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
            'moderation_mode' => $this->moderation_mode?->value,
            'commercial_mode' => $this->commercial_mode?->value,
            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'location_name' => $this->location_name,
            'cover_image_path' => $this->cover_image_path,
            'cover_image_url' => $this->assetUrl($this->cover_image_path),
            'primary_color' => $this->primary_color,
            'secondary_color' => $this->secondary_color,
            'public_url' => $this->publicHubUrl(),
            'upload_url' => $this->publicUploadUrl(),
            'created_at' => $this->created_at?->toISOString(),
            'organization_name' => $organizationName,
            'client_name' => $this->client?->name,
            'enabled_modules' => $this->whenLoaded('modules', fn () => $this->modules
                ->where('is_enabled', true)
                ->pluck('module_key')
                ->values()
                ->all()),
            'media_count' => $this->whenCounted('media'),
            'wall' => $this->whenLoaded('wallSettings', fn () => $this->wallSettings ? [
                'id' => $this->wallSettings->id,
                'wall_code' => $this->wallSettings->wall_code,
                'is_enabled' => (bool) $this->wallSettings->is_enabled,
                'status' => $this->wallSettings->status?->value,
                'public_url' => $this->wallSettings->publicUrl(),
            ] : null),
        ];
    }

    private function assetUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $path) === 1) {
            return $path;
        }

        $url = Storage::disk('public')->url($path);

        return preg_match('/^https?:\/\//i', $url) === 1
            ? $url
            : url($url);
    }
}
