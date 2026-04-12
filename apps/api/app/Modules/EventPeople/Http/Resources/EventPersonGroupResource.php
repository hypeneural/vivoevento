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
        $groupStat = $this->relationLoaded('groupStat') ? $this->groupStat : null;
        $groupMediaStat = $this->relationLoaded('groupMediaStat') ? $this->groupMediaStat : null;

        $activeMemberships = $memberships->where('status', 'active')->values();
        $people = $activeMemberships
            ->map(fn (EventPersonGroupMembership $membership) => $membership->person)
            ->filter()
            ->values();

        $memberCount = $groupStat?->member_count ?? $activeMemberships->count();
        $peopleWithPrimaryPhotoCount = $groupStat?->people_with_primary_photo_count
            ?? $people->filter(fn ($person) => (bool) $person->primary_reference_photo_id)->count();
        $peopleWithMediaCount = $groupStat?->people_with_media_count
            ?? $people->filter(fn ($person) => ((int) optional($person->mediaStats->first())->media_count) > 0)->count();
        $mediaCount = $groupMediaStat?->media_count
            ?? $people->sum(fn ($person) => (int) optional($person->mediaStats->first())->media_count);
        $publishedMediaCount = $groupMediaStat?->published_media_count
            ?? $people->sum(fn ($person) => (int) optional($person->mediaStats->first())->published_media_count);

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
                'member_count' => (int) $memberCount,
                'people_with_primary_photo_count' => (int) $peopleWithPrimaryPhotoCount,
                'people_with_media_count' => (int) $peopleWithMediaCount,
                'media_count' => (int) $mediaCount,
                'published_media_count' => (int) $publishedMediaCount,
            ],
            'memberships' => EventPersonGroupMembershipResource::collection($memberships),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
