<?php

namespace App\Modules\Wall\Http\Resources;

use App\Modules\Wall\Services\WallBroadcasterService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Resource for the wall boot endpoint.
 * Returns everything the wall player needs to initialize.
 *
 * @mixin \App\Modules\Wall\Models\EventWallSetting
 */
class WallBootResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $event = $this->event;
        $broadcaster = app(WallBroadcasterService::class);

        // Media items loaded externally and set as a relation
        $files = $event && $event->relationLoaded('media')
            ? WallMediaResource::collection($event->media)->resolve($request)
            : [];

        return [
            'event' => [
                'id'        => $event?->id,
                'title'     => $event?->title,
                'slug'      => $event?->slug,
                'wall_code' => $this->wall_code,
                'status'    => $this->publicStatus(),
            ],
            'files'    => $files,
            'settings' => $broadcaster->settingsPayload($this->resource),
        ];
    }
}
