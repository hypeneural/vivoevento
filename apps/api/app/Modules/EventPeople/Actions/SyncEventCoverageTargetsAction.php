<?php

namespace App\Modules\EventPeople\Actions;

use App\Modules\EventPeople\Enums\EventCoverageTargetType;
use App\Modules\EventPeople\Models\EventCoverageTarget;
use App\Modules\EventPeople\Models\EventMustHavePair;
use App\Modules\EventPeople\Models\EventPerson;
use App\Modules\EventPeople\Models\EventPersonGroup;
use App\Modules\EventPeople\Services\EventPeoplePresetCatalog;
use App\Modules\EventPeople\Support\EventPeopleRoleResolver;
use App\Modules\EventPeople\Support\PersonPairKey;
use App\Modules\Events\Models\Event;
use App\Modules\Users\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SyncEventCoverageTargetsAction
{
    public function __construct(
        private readonly EventPeoplePresetCatalog $presetCatalog,
        private readonly EventPeopleRoleResolver $roleResolver,
    ) {}

    /**
     * @return Collection<int, EventCoverageTarget>
     */
    public function execute(Event $event, User $user): Collection
    {
        $presetPackage = $this->presetCatalog->forEventType($event->event_type?->value ?? $event->event_type);
        $coverageSeeds = collect($presetPackage['coverage_targets'] ?? []);
        $presetPeople = $this->roleResolver->presetPeopleForEvent($event);

        /** @var Collection<int, EventPerson> $people */
        $people = EventPerson::query()
            ->where('event_id', $event->id)
            ->orderByDesc('importance_rank')
            ->get();

        $groupsBySlug = EventPersonGroup::query()
            ->forEvent($event->id)
            ->get()
            ->keyBy('slug');

        $targets = collect();

        DB::transaction(function () use ($coverageSeeds, $event, $groupsBySlug, $people, $presetPeople, $targets, $user): void {
            foreach ($coverageSeeds as $seed) {
                $targetType = EventCoverageTargetType::tryFrom((string) ($seed['target_type'] ?? 'person'))
                    ?? EventCoverageTargetType::Person;
                $roleKeys = array_values($seed['role_keys'] ?? []);
                $groupKey = $seed['group_key'] ?? null;
                $matchedPeople = $this->roleResolver->matchPeopleByRoleKeys($people, $presetPeople, $roleKeys);
                $personA = $matchedPeople->first();
                $personB = $matchedPeople->skip(1)->first();
                $group = $groupKey ? $groupsBySlug->get($groupKey) : null;

                $target = EventCoverageTarget::query()->firstOrNew([
                    'event_id' => $event->id,
                    'key' => (string) $seed['key'],
                ]);

                $personAId = in_array($targetType, [EventCoverageTargetType::Person, EventCoverageTargetType::Pair], true)
                    ? $personA?->id
                    : null;
                $personBId = $targetType === EventCoverageTargetType::Pair ? $personB?->id : null;
                $groupId = $targetType === EventCoverageTargetType::Group ? $group?->id : null;

                $target->fill([
                    'label' => (string) ($seed['label'] ?? 'Cobertura importante'),
                    'target_type' => $targetType->value,
                    'person_a_id' => $personAId,
                    'person_b_id' => $personBId,
                    'event_person_group_id' => $groupId,
                    'required_media_count' => $this->requiredMediaCount($targetType),
                    'required_published_media_count' => $this->requiredPublishedMediaCount($targetType),
                    'importance_rank' => (int) ($seed['priority'] ?? 0),
                    'source' => 'preset',
                    'status' => 'active',
                    'metadata' => [
                        'role_keys' => $roleKeys,
                        'group_key' => $groupKey,
                        'seed_priority' => (int) ($seed['priority'] ?? 0),
                    ],
                    'updated_by' => $user->id,
                    'created_by' => $target->exists ? $target->created_by : $user->id,
                ]);

                $target->save();
                $targets->push($target);

                if ($targetType === EventCoverageTargetType::Pair && $personA && $personB) {
                    EventMustHavePair::query()->updateOrCreate(
                        [
                            'event_id' => $event->id,
                            'person_pair_key' => PersonPairKey::make($personA->id, $personB->id),
                        ],
                        [
                            'person_a_id' => $personA->id,
                            'person_b_id' => $personB->id,
                            'label' => (string) ($seed['label'] ?? "{$personA->display_name} + {$personB->display_name}"),
                            'required_media_count' => $this->requiredMediaCount($targetType),
                            'importance_rank' => (int) ($seed['priority'] ?? 0),
                            'status' => 'active',
                            'updated_by' => $user->id,
                            'created_by' => $user->id,
                        ],
                    );
                }
            }
        });

        return $targets;
    }

    private function requiredMediaCount(EventCoverageTargetType $targetType): int
    {
        return match ($targetType) {
            EventCoverageTargetType::Pair => 2,
            EventCoverageTargetType::Group => 4,
            EventCoverageTargetType::Person => 1,
        };
    }

    private function requiredPublishedMediaCount(EventCoverageTargetType $targetType): int
    {
        return match ($targetType) {
            EventCoverageTargetType::Group => 1,
            default => 0,
        };
    }
}
