<?php

namespace App\Modules\Gallery\Http\Resources;

use App\Modules\Gallery\Support\GalleryBuilderAssetUrlResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventGallerySettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $previewUrl = null;

        if (filled($this->preview_share_token)) {
            $previewUrl = rtrim((string) config('app.url'), '/').'/api/v1/public/gallery-previews/'.$this->preview_share_token;
        }

        $pageSchema = is_array($this->page_schema_json) ? $this->page_schema_json : [];
        $pageSchema = app(GalleryBuilderAssetUrlResolver::class)->hydratePageSchema($pageSchema);

        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'is_enabled' => (bool) $this->is_enabled,
            'event_type_family' => $this->event_type_family,
            'style_skin' => $this->style_skin,
            'behavior_profile' => $this->behavior_profile,
            'theme_key' => $this->theme_key,
            'layout_key' => $this->layout_key,
            'theme_tokens' => is_array($this->theme_tokens_json) ? $this->theme_tokens_json : [],
            'page_schema' => $pageSchema,
            'media_behavior' => is_array($this->media_behavior_json) ? $this->media_behavior_json : [],
            'current_preset_origin' => $this->normalizePresetOrigin($this->current_preset_origin_json),
            'current_draft_revision_id' => $this->current_draft_revision_id,
            'current_published_revision_id' => $this->current_published_revision_id,
            'preview_revision_id' => $this->preview_revision_id,
            'draft_version' => $this->draft_version,
            'published_version' => $this->published_version,
            'preview_share_token' => $this->preview_share_token,
            'preview_url' => $previewUrl,
            'preview_share_expires_at' => $this->preview_share_expires_at?->toIso8601String(),
            'last_autosaved_at' => $this->last_autosaved_at?->toIso8601String(),
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  mixed  $origin
     * @return array<string, mixed>|null
     */
    private function normalizePresetOrigin(mixed $origin): ?array
    {
        if (! is_array($origin)) {
            return null;
        }

        $appliedBy = is_array($origin['applied_by'] ?? null) ? $origin['applied_by'] : null;

        return [
            'origin_type' => isset($origin['origin_type']) ? (string) $origin['origin_type'] : null,
            'key' => isset($origin['key']) ? (string) $origin['key'] : null,
            'label' => isset($origin['label']) ? (string) $origin['label'] : null,
            'applied_at' => isset($origin['applied_at']) ? (string) $origin['applied_at'] : null,
            'applied_by' => $appliedBy
                ? [
                    'id' => isset($appliedBy['id']) ? (int) $appliedBy['id'] : null,
                    'name' => isset($appliedBy['name']) ? (string) $appliedBy['name'] : null,
                ]
                : null,
        ];
    }
}
