<?php

namespace App\Modules\EventPeople\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventPersonMediaStatResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'media_count' => $this->media_count,
            'solo_media_count' => $this->solo_media_count,
            'with_others_media_count' => $this->with_others_media_count,
            'published_media_count' => $this->published_media_count,
            'pending_media_count' => $this->pending_media_count,
            'best_media_id' => $this->best_media_id,
            'latest_media_id' => $this->latest_media_id,
            'projected_at' => $this->projected_at?->toIso8601String(),
        ];
    }
}
