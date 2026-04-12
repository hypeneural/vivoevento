<?php

namespace App\Modules\EventPeople\Actions;

use App\Modules\EventPeople\Models\EventPerson;
use App\Modules\EventPeople\Models\EventPersonGroup;
use App\Modules\EventPeople\Models\EventPersonGroupMediaStat;
use App\Modules\EventPeople\Models\EventPersonGroupMembership;
use App\Modules\EventPeople\Models\EventPersonGroupStat;
use App\Modules\EventPeople\Models\EventPersonMediaStat;
use App\Modules\Events\Models\Event;
use Illuminate\Support\Collection;

class ProjectEventPersonGroupStatsAction
{
    public function executeForEvent(Event $event): void
    {
        EventPersonGroup::query()
            ->forEvent($event->id)
            ->orderBy('id')
            ->chunk(200, function ($groups): void {
                foreach ($groups as $group) {
                    $this->executeForGroup($group);
                }
            });
    }

    public function executeForGroup(EventPersonGroup $group): void
    {
        $memberIds = EventPersonGroupMembership::query()
            ->where('event_person_group_id', $group->id)
            ->where('status', 'active')
            ->pluck('event_person_id')
            ->map(fn ($id): int => (int) $id);

        $memberCount = $memberIds->count();

        /** @var Collection<int, EventPerson> $people */
        $people = $memberIds->isEmpty()
            ? collect()
            : EventPerson::query()
                ->whereIn('id', $memberIds->all())
                ->get(['id', 'primary_reference_photo_id']);

        $mediaStats = $memberIds->isEmpty()
            ? collect()
            : EventPersonMediaStat::query()
                ->where('event_id', $group->event_id)
                ->whereIn('event_person_id', $memberIds->all())
                ->get()
                ->keyBy('event_person_id');

        $peopleWithPrimaryPhotoCount = $people
            ->filter(function (EventPerson $person) use ($mediaStats): bool {
                $stat = $mediaStats->get($person->id);

                return (bool) ($person->primary_reference_photo_id
                    || $stat?->best_media_id
                    || $stat?->latest_media_id);
            })
            ->count();

        $peopleWithMediaCount = $people
            ->filter(function (EventPerson $person) use ($mediaStats): bool {
                $stat = $mediaStats->get($person->id);

                return (int) ($stat?->media_count ?? 0) > 0;
            })
            ->count();

        $mediaCount = $mediaStats->sum(fn ($stat): int => (int) $stat->media_count);
        $publishedMediaCount = $mediaStats->sum(fn ($stat): int => (int) $stat->published_media_count);

        EventPersonGroupStat::query()->updateOrCreate(
            [
                'event_id' => $group->event_id,
                'event_person_group_id' => $group->id,
            ],
            [
                'member_count' => $memberCount,
                'people_with_primary_photo_count' => $peopleWithPrimaryPhotoCount,
                'people_with_media_count' => $peopleWithMediaCount,
                'projected_at' => now(),
            ],
        );

        EventPersonGroupMediaStat::query()->updateOrCreate(
            [
                'event_id' => $group->event_id,
                'event_person_group_id' => $group->id,
            ],
            [
                'media_count' => $mediaCount,
                'published_media_count' => $publishedMediaCount,
                'projected_at' => now(),
            ],
        );
    }
}
