<?php

namespace App\Modules\Play\Http\Resources;

use App\Modules\MediaProcessing\Services\MediaAssetUrlService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayGameAssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $resolver = app(MediaAssetUrlService::class);

        return [
            'id' => $this->id,
            'media_id' => $this->media_id,
            'role' => $this->role,
            'sort_order' => $this->sort_order,
            'metadata' => $this->metadata_json ?? [],
            'media' => $this->whenLoaded('media', fn () => [
                'id' => $this->media?->id,
                'thumbnail_url' => $this->media ? $resolver->resolve($this->media) : null,
                'mime_type' => $this->media?->mime_type,
                'width' => $this->media?->width,
                'height' => $this->media?->height,
            ]),
        ];
    }
}
