<?php

namespace App\Modules\EventPeople\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventRelationalCollectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $items = $this->relationLoaded('items') ? $this->items : collect();

        return [
            'id' => $this->id,
            'collection_key' => $this->collection_key,
            'collection_type' => $this->collection_type?->value ?? $this->collection_type,
            'source_type' => $this->source_type,
            'display_name' => $this->display_name,
            'status' => $this->status?->value ?? $this->status,
            'visibility' => $this->visibility?->value ?? $this->visibility,
            'share_token' => $this->share_token,
            'public_url' => $this->publicUrl(),
            'public_api_url' => $this->publicApiUrl(),
            'item_count' => $items->count(),
            'published_item_count' => $items->where('is_published', true)->count(),
            'person_a' => $this->personA ? [
                'id' => $this->personA->id,
                'display_name' => $this->personA->display_name,
                'type' => $this->personA->type?->value ?? $this->personA->type,
            ] : null,
            'person_b' => $this->personB ? [
                'id' => $this->personB->id,
                'display_name' => $this->personB->display_name,
                'type' => $this->personB->type?->value ?? $this->personB->type,
            ] : null,
            'group' => $this->group ? [
                'id' => $this->group->id,
                'display_name' => $this->group->display_name,
                'slug' => $this->group->slug,
                'group_type' => $this->group->group_type,
            ] : null,
            'metadata' => $this->metadata ?? [],
            'generated_at' => $this->generated_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'items' => EventRelationalCollectionItemResource::collection($items),
        ];
    }
}
