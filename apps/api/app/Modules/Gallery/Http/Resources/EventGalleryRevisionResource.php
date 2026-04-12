<?php

namespace App\Modules\Gallery\Http\Resources;

use App\Modules\Gallery\Support\GalleryBuilderAssetUrlResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventGalleryRevisionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pageSchema = is_array($this->page_schema_json) ? $this->page_schema_json : [];
        $pageSchema = app(GalleryBuilderAssetUrlResolver::class)->hydratePageSchema($pageSchema);

        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'version_number' => $this->version_number,
            'kind' => $this->kind,
            'event_type_family' => $this->event_type_family,
            'style_skin' => $this->style_skin,
            'behavior_profile' => $this->behavior_profile,
            'theme_key' => $this->theme_key,
            'layout_key' => $this->layout_key,
            'theme_tokens' => is_array($this->theme_tokens_json) ? $this->theme_tokens_json : [],
            'page_schema' => $pageSchema,
            'media_behavior' => is_array($this->media_behavior_json) ? $this->media_behavior_json : [],
            'change_summary' => is_array($this->change_summary_json) ? $this->change_summary_json : null,
            'creator' => $this->creator
                ? [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                ]
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
