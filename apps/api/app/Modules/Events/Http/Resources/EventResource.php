<?php

namespace App\Modules\Events\Http\Resources;

use App\Modules\ContentModeration\Http\Resources\EventContentModerationSettingResource;
use App\Modules\MediaIntelligence\Http\Resources\EventMediaIntelligenceSettingResource;
use App\Modules\Events\Support\EventIntakeBlacklistStateBuilder;
use App\Modules\Events\Support\EventBrandingResolver;
use App\Modules\Events\Support\EventIntakeChannelsStateBuilder;
use App\Modules\FaceSearch\Http\Resources\EventFaceSearchSettingResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $organizationName = $this->organization?->trade_name
            ?? $this->organization?->legal_name
            ?? $this->organization?->name;
        $effectiveBranding = app(EventBrandingResolver::class)->resolve($this->resource);
        $intakeState = app(EventIntakeChannelsStateBuilder::class)->build($this->resource);
        $blacklistState = app(EventIntakeBlacklistStateBuilder::class)->build($this->resource);

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
            'description' => $this->description,
            'cover_image_path' => $this->cover_image_path,
            'cover_image_url' => $this->assetUrl($this->cover_image_path),
            'logo_path' => $this->logo_path,
            'logo_url' => $this->assetUrl($this->logo_path),
            'qr_code_path' => $this->qr_code_path,
            'primary_color' => $this->primary_color,
            'secondary_color' => $this->secondary_color,
            'inherit_branding' => (bool) ($this->inherit_branding ?? true),
            'effective_branding' => $effectiveBranding,
            'public_url' => $this->publicHubUrl(),
            'upload_url' => $this->publicUploadUrl(),
            'upload_api_url' => $this->publicUploadApiUrl(),
            'retention_days' => $this->retention_days,
            'current_entitlements' => $this->current_entitlements_json,
            'intake_defaults' => $intakeState['intake_defaults'],
            'intake_channels' => $intakeState['intake_channels'],
            'intake_blacklist' => $blacklistState['intake_blacklist'],
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'organization_name' => $organizationName,
            'organization_slug' => $this->organization?->slug,
            'client_name' => $this->client?->name,
            'content_moderation' => $this->whenLoaded('contentModerationSettings', fn () => $this->contentModerationSettings
                ? (new EventContentModerationSettingResource($this->contentModerationSettings))->toArray($request)
                : null),
            'face_search' => $this->whenLoaded('faceSearchSettings', fn () => $this->faceSearchSettings
                ? (new EventFaceSearchSettingResource($this->faceSearchSettings))->toArray($request)
                : null),
            'media_intelligence' => $this->whenLoaded('mediaIntelligenceSettings', fn () => $this->mediaIntelligenceSettings
                ? (new EventMediaIntelligenceSettingResource($this->mediaIntelligenceSettings))->toArray($request)
                : null),
            'enabled_modules' => $this->whenLoaded('modules', fn () => $this->modules
                ->where('is_enabled', true)
                ->pluck('module_key')
                ->values()
                ->all()),
            'module_count' => $this->whenLoaded('modules', fn () => $this->modules
                ->where('is_enabled', true)
                ->count()),
            'wall' => $this->whenLoaded('wallSettings', fn () => $this->wallSettings ? [
                'id' => $this->wallSettings->id,
                'wall_code' => $this->wallSettings->wall_code,
                'is_enabled' => (bool) $this->wallSettings->is_enabled,
                'status' => $this->wallSettings->status?->value,
                'public_url' => $this->wallSettings->publicUrl(),
            ] : null),
            'client' => $this->whenLoaded('client'),
            'modules' => $this->whenLoaded('modules'),
            'channels' => $this->whenLoaded('channels'),
            'banners' => $this->whenLoaded('banners'),
            'team_members' => $this->whenLoaded('teamMembers'),
            'media_count' => $this->whenCounted('media'),
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
