<?php

namespace App\Modules\Gallery\Http\Resources;

use App\Modules\Gallery\Support\GalleryBuilderAssetUrlResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GalleryPresetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pageSchema = is_array($this->page_schema_json) ? $this->page_schema_json : [];
        $pageSchema = app(GalleryBuilderAssetUrlResolver::class)->hydratePageSchema($pageSchema);

        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'event_type_family' => $this->event_type_family,
            'style_skin' => $this->style_skin,
            'behavior_profile' => $this->behavior_profile,
            'theme_key' => $this->theme_key,
            'layout_key' => $this->layout_key,
            'derived_preset_key' => $this->derived_preset_key,
            'source_event' => $this->sourceEvent
                ? [
                    'id' => $this->sourceEvent->id,
                    'title' => $this->sourceEvent->title,
                    'slug' => $this->sourceEvent->slug,
                ]
                : null,
            'creator' => $this->creator
                ? [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                ]
                : null,
            'payload' => [
                'theme_tokens' => is_array($this->theme_tokens_json) ? $this->theme_tokens_json : [],
                'page_schema' => $pageSchema,
                'media_behavior' => is_array($this->media_behavior_json) ? $this->media_behavior_json : [],
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
