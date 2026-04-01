<?php

namespace App\Modules\Wall\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for individual media items in the wall player.
 *
 * @mixin \App\Modules\MediaProcessing\Models\EventMedia
 */
class WallMediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $broadcaster = app(\App\Modules\Wall\Services\WallBroadcasterService::class);

        return $broadcaster->mediaPayload($this->resource);
    }
}
