<?php

namespace App\Modules\EventPeople\Http\Resources;

use App\Modules\MediaProcessing\Services\MediaAssetUrlService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventRelationalCollectionItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $assets = app(MediaAssetUrlService::class);

        return [
            'id' => $this->id,
            'event_media_id' => $this->event_media_id,
            'sort_order' => $this->sort_order,
            'match_score' => $this->match_score,
            'matched_people_count' => $this->matched_people_count,
            'is_published' => (bool) $this->is_published,
            'media' => $this->whenLoaded('media', fn (): ?array => $this->media ? [
                'id' => $this->media->id,
                'caption' => $this->media->caption,
                'preview_url' => is_array($preview = $assets->previewAsset($this->media))
                    ? ($preview['url'] ?? null)
                    : null,
                'thumbnail_url' => is_array($thumbnail = $assets->thumbnailAsset($this->media))
                    ? ($thumbnail['url'] ?? null)
                    : null,
                'original_url' => $assets->original($this->media),
                'publication_status' => $this->media->publication_status?->value ?? $this->media->publication_status,
                'moderation_status' => $this->media->moderation_status?->value ?? $this->media->moderation_status,
                'created_at' => $this->media->created_at?->toIso8601String(),
            ] : null),
        ];
    }
}
