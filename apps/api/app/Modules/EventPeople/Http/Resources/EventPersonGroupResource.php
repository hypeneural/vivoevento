<?php

namespace App\Modules\EventPeople\Http\Resources;

use App\Modules\EventPeople\Models\EventPersonGroupMembership;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventPersonGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $memberships = $this->relationLoaded('memberships')
            ? $this->memberships
            : collect();

        $activeMemberships = $memberships->where('status', 'active')->values();
        $people = $activeMemberships
            ->map(fn (EventPersonGroupMembership $membership) => $membership->person)
            ->filter()
            ->values();

        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'display_name' => $this->display_name,
            'slug' => $this->slug,
            'group_type' => $this->group_type,
            'side' => $this->side?->value ?? $this->side,
            'notes' => $this->notes,
            'importance_rank' => $this->importance_rank,
            'status' => $this->status,
            'stats' => [
                'member_count' => $activeMemberships->count(),
                'people_with_primary_photo_count' => $people->filter(fn ($person) => (bool) $person->primary_reference_photo_id)->count(),
                'people_with_media_count' => $people->filter(fn ($person) => ((int) optional($person->mediaStats->first())->media_count) > 0)->count(),
                'media_count' => $people->sum(fn ($person) => (int) optional($person->mediaStats->first())->media_count),
                'published_media_count' => $people->sum(fn ($person) => (int) optional($person->mediaStats->first())->published_media_count),
            ],
            'memberships' => EventPersonGroupMembershipResource::collection($memberships),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
