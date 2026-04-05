<?php

namespace App\Modules\Wall\Http\Resources;

use App\Modules\Wall\Services\WallPayloadFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
        $payloads = app(WallPayloadFactory::class);

        $files = $event && $event->relationLoaded('media')
            ? WallMediaResource::collection($event->media)->resolve($request)
            : [];

        return [
            'event' => [
                'id'        => $event?->id,
                'title'     => $event?->title,
                'slug'      => $event?->slug,
                'upload_url' => $event?->isModuleEnabled('live') ? $event->publicUploadUrl() : null,
                'wall_code' => $this->wall_code,
                'status'    => $this->publicStatus(),
            ],
            'files' => $files,
            'settings' => $payloads->settings($this->resource, runtime: true),
        ];
    }
}
