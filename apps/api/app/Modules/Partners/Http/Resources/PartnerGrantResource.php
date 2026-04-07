<?php

namespace App\Modules\Partners\Http\Resources;

use App\Modules\Billing\Models\EventAccessGrant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartnerGrantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var EventAccessGrant $grant */
        $grant = $this->resource;

        return [
            'id' => $grant->id,
            'event_id' => $grant->event_id,
            'source_type' => $grant->source_type?->value ?? $grant->source_type,
            'status' => $grant->status?->value ?? $grant->status,
            'priority' => $grant->priority,
            'merge_strategy' => $grant->merge_strategy?->value ?? $grant->merge_strategy,
            'notes' => $grant->notes,
            'features' => $grant->features_snapshot_json,
            'limits' => $grant->limits_snapshot_json,
            'starts_at' => $grant->starts_at?->toISOString(),
            'ends_at' => $grant->ends_at?->toISOString(),
            'event' => $grant->event ? [
                'id' => $grant->event->id,
                'title' => $grant->event->title,
                'slug' => $grant->event->slug,
            ] : null,
            'granted_by' => $grant->grantedBy ? [
                'id' => $grant->grantedBy->id,
                'name' => $grant->grantedBy->name,
                'email' => $grant->grantedBy->email,
            ] : null,
            'created_at' => $grant->created_at?->toISOString(),
            'updated_at' => $grant->updated_at?->toISOString(),
        ];
    }
}
