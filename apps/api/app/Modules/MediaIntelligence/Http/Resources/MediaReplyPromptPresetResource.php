<?php

namespace App\Modules\MediaIntelligence\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaReplyPromptPresetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'category' => $this->category,
            'category_entry' => $this->whenLoaded('categoryEntry', fn () => $this->categoryEntry
                ? (new MediaReplyPromptCategoryResource($this->categoryEntry))->resolve()
                : null),
            'description' => $this->description,
            'prompt_template' => $this->prompt_template,
            'sort_order' => $this->sort_order,
            'is_active' => (bool) $this->is_active,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
