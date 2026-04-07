<?php

namespace App\Modules\Partners\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Activitylog\Models\Activity;

class PartnerActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Activity $activity */
        $activity = $this->resource;

        return [
            'id' => $activity->id,
            'event' => $activity->event,
            'description' => $activity->description,
            'actor' => $activity->causer ? [
                'id' => $activity->causer->id,
                'name' => $activity->causer->name,
                'email' => $activity->causer->email,
            ] : null,
            'properties' => $activity->properties?->toArray() ?? [],
            'created_at' => $activity->created_at?->toISOString(),
        ];
    }
}
