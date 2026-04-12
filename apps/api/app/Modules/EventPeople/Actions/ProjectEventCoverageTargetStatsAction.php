<?php

namespace App\Modules\EventPeople\Actions;

use App\Modules\EventPeople\Enums\EventCoverageTargetType;
use App\Modules\EventPeople\Models\EventCoverageTarget;
use App\Modules\EventPeople\Models\EventCoverageTargetStat;
use App\Modules\EventPeople\Models\EventPersonMediaStat;
use App\Modules\EventPeople\Models\EventPersonPairScore;
use App\Modules\EventPeople\Models\EventPersonRelation;
use App\Modules\EventPeople\Services\EventCoverageScoringService;
use App\Modules\EventPeople\Support\PersonPairKey;
use App\Modules\Events\Models\Event;
use Illuminate\Support\Collection;

class ProjectEventCoverageTargetStatsAction
{
    public function __construct(
        private readonly EventCoverageScoringService $scoring,
    ) {}

    public function executeForEvent(Event $event): void
    {
        EventCoverageTarget::query()
            ->forEvent($event->id)
            ->with(['personA', 'personB', 'group', 'group.groupStat', 'group.groupMediaStat'])
            ->orderBy('id')
            ->chunk(200, function ($targets) use ($event): void {
                foreach ($targets as $target) {
                    $this->executeForTarget($event, $target);
                }
            });
    }

    public function executeForTarget(Event $event, EventCoverageTarget $target): void
    {
        $payload = match ($target->target_type?->value ?? $target->target_type) {
            EventCoverageTargetType::Pair->value => $this->scorePairTarget($event, $target),
            EventCoverageTargetType::Group->value => $this->scoreGroupTarget($target),
            default => $this->scorePersonTarget($event, $target),
        };

        EventCoverageTargetStat::query()->updateOrCreate(
            [
                'event_id' => $event->id,
                'event_coverage_target_id' => $target->id,
            ],
            array_merge($payload, [
                'projected_at' => now(),
            ]),
        );

        $target->forceFill(['last_evaluated_at' => now()])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function scorePersonTarget(Event $event, EventCoverageTarget $target): array
    {
        if (! $target->personA) {
            return $this->scoring->scorePerson(0, 0, false, $target->required_media_count, $target->required_published_media_count, 0);
        }

        $stat = EventPersonMediaStat::query()
            ->where('event_id', $event->id)
            ->where('event_person_id', $target->person_a_id)
            ->first();

        $hasPrimaryPhoto = (bool) ($target->personA->primary_reference_photo_id
            || $stat?->best_media_id
            || $stat?->latest_media_id);

        return $this->scoring->scorePerson(
            (int) ($stat?->media_count ?? 0),
            (int) ($stat?->published_media_count ?? 0),
            $hasPrimaryPhoto,
            $target->required_media_count,
            $target->required_published_media_count,
            1,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function scorePairTarget(Event $event, EventCoverageTarget $target): array
    {
        if (! $target->personA || ! $target->personB) {
            return $this->scoring->scorePair(0, false, $target->required_media_count, 0);
        }

        $pairKey = PersonPairKey::make($target->personA->id, $target->personB->id);
        $pairScore = EventPersonPairScore::query()
            ->where('event_id', $event->id)
            ->where('person_pair_key', $pairKey)
            ->first();

        $hasPrimaryRelation = EventPersonRelation::query()
            ->where('event_id', $event->id)
            ->where('person_pair_key', $pairKey)
            ->where('is_primary', true)
            ->exists();

        return $this->scoring->scorePair(
            (int) ($pairScore?->co_media_count ?? 0),
            $hasPrimaryRelation,
            $target->required_media_count,
            2,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function scoreGroupTarget(EventCoverageTarget $target): array
    {
        $group = $target->group;
        $groupStat = $group?->groupStat;
        $groupMediaStat = $group?->groupMediaStat;

        if (! $group || ! $groupStat || ! $groupMediaStat) {
            return $this->scoring->scoreGroup(0, 0, 0, 0, $target->required_media_count, $target->required_published_media_count);
        }

        return $this->scoring->scoreGroup(
            (int) $groupStat->member_count,
            (int) $groupStat->people_with_primary_photo_count,
            (int) $groupMediaStat->media_count,
            (int) $groupMediaStat->published_media_count,
            $target->required_media_count,
            $target->required_published_media_count,
        );
    }
}
