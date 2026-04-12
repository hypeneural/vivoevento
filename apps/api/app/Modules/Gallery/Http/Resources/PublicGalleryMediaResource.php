<?php

declare(strict_types=1);

namespace App\Modules\Gallery\Http\Resources;

use App\Modules\Gallery\Support\GalleryResponsiveSourceBuilder;
use App\Modules\MediaProcessing\Http\Resources\EventMediaResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicGalleryMediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $payload = (new EventMediaResource($this->resource))->toArray($request);

        $payload['responsive_sources'] = app(GalleryResponsiveSourceBuilder::class)->build($this->resource);

        return $payload;
    }
}
