<?php

namespace App\Modules\EventPeople\Http\Resources;

use App\Modules\MediaProcessing\Services\MediaAssetUrlService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventPersonReferencePhotoCandidateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $media = $this->face?->media;
        $assets = app(MediaAssetUrlService::class);
        $thumbnail = $media ? $assets->thumbnailAsset($media) : null;
        $preview = $media ? $assets->previewAsset($media) : null;

        return [
            'assignment_id' => $this->id,
            'event_media_face_id' => $this->event_media_face_id,
            'event_media_id' => $this->face?->event_media_id,
            'face_index' => $this->face?->face_index,
            'quality_score' => $this->face?->quality_score,
            'quality_tier' => $this->face?->quality_tier,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'media' => $media ? [
                'id' => $media->id,
                'caption' => $media->caption,
                'thumbnail_url' => is_array($thumbnail) ? ($thumbnail['url'] ?? null) : null,
                'preview_url' => is_array($preview) ? ($preview['url'] ?? null) : null,
                'original_url' => $assets->original($media),
                'created_at' => $media->created_at?->toIso8601String(),
            ] : null,
        ];
    }
}
