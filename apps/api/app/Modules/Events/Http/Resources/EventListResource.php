<?php

namespace App\Modules\Events\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class EventListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $payload = (new EventResource($this->resource))->toArray($request);

        return Arr::only($payload, [
            'id',
            'uuid',
            'organization_id',
            'client_id',
            'title',
            'slug',
            'upload_slug',
            'event_type',
            'status',
            'visibility',
            'moderation_mode',
            'commercial_mode',
            'starts_at',
            'ends_at',
            'location_name',
            'cover_image_path',
            'cover_image_url',
            'primary_color',
            'secondary_color',
            'public_url',
            'upload_url',
            'created_at',
            'organization_name',
            'client_name',
            'enabled_modules',
            'media_count',
            'wall',
        ]);
    }
}
