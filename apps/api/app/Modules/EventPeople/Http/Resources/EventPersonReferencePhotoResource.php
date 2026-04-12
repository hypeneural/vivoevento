<?php

namespace App\Modules\EventPeople\Http\Resources;

use App\Modules\MediaProcessing\Services\MediaAssetUrlService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventPersonReferencePhotoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $assets = app(MediaAssetUrlService::class);

        return [
            'id' => $this->id,
            'source' => $this->source?->value ?? $this->source,
            'event_media_id' => $this->event_media_id,
            'event_media_face_id' => $this->event_media_face_id,
            'reference_upload_media_id' => $this->reference_upload_media_id,
            'purpose' => $this->purpose?->value ?? $this->purpose,
            'status' => $this->status?->value ?? $this->status,
            'quality_score' => $this->quality_score,
            'is_primary_avatar' => (bool) $this->is_primary_avatar,
            'face' => $this->whenLoaded('face', fn (): array => [
                'id' => $this->face?->id,
                'event_media_id' => $this->face?->event_media_id,
                'face_index' => $this->face?->face_index,
                'quality_score' => $this->face?->quality_score,
                'quality_tier' => $this->face?->quality_tier,
            ]),
            'upload_media' => $this->whenLoaded('uploadMedia', fn (): ?array => $this->uploadMedia ? [
                'id' => $this->uploadMedia->id,
                'original_filename' => $this->uploadMedia->displayFilename(),
                'preview_url' => is_array($preview = $assets->previewAsset($this->uploadMedia))
                    ? ($preview['url'] ?? null)
                    : null,
                'original_url' => $assets->original($this->uploadMedia),
            ] : null),
        ];
    }
}
