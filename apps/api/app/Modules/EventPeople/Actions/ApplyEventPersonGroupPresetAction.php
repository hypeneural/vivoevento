<?php

namespace App\Modules\EventPeople\Actions;

use App\Modules\EventPeople\Models\EventPersonGroup;
use App\Modules\EventPeople\Models\EventPersonGroupMembership;
use App\Modules\EventPeople\Models\EventPerson;
use App\Modules\EventPeople\Services\EventPeoplePresetCatalog;
use App\Modules\EventPeople\Support\EventPeopleRoleResolver;
use App\Modules\Events\Models\Event;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ApplyEventPersonGroupPresetAction
{
    public function __construct(
        private readonly EventPeoplePresetCatalog $catalog,
        private readonly EventPeopleRoleResolver $roleResolver,
    ) {}

    public function execute(Event $event, User $user): Collection
    {
        $preset = $this->catalog->forEventType($event->event_type?->value ?? $event->event_type);
        $groupSeeds = collect($preset['groups'] ?? []);
        $presetPeople = $this->roleResolver->presetPeopleForEvent($event);
        $peopleByRole = $presetPeople->keyBy('role_key');
        $people = EventPerson::query()
            ->where('event_id', $event->id)
            ->orderByDesc('importance_rank')
            ->get();

        DB::transaction(function () use ($event, $groupSeeds, $peopleByRole, $people, $user): void {
            foreach ($groupSeeds as $seed) {
                $group = EventPersonGroup::query()->firstOrCreate(
                    [
                        'event_id' => $event->id,
                        'slug' => (string) $seed['key'],
                    ],
                    [
                        'display_name' => (string) $seed['label'],
                        'group_type' => (string) ($seed['role_family'] ?? 'custom'),
                        'side' => $this->resolveSide((array) ($seed['member_role_keys'] ?? []), $peopleByRole),
                        'importance_rank' => (int) ($seed['importance_rank'] ?? 0),
                        'status' => 'active',
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                    ],
                );

                $memberRoleKeys = array_values($seed['member_role_keys'] ?? []);
                if (! empty($memberRoleKeys)) {
                    $matches = $this->roleResolver->matchPeopleByRoleKeys($people, $peopleByRole->values(), $memberRoleKeys);

                    foreach ($matches as $person) {
                        EventPersonGroupMembership::query()->firstOrCreate(
                            [
                                'event_id' => $event->id,
                                'event_person_group_id' => $group->id,
                                'event_person_id' => $person->id,
                            ],
                            [
                                'role_label' => $person->display_name,
                                'source' => 'preset_seed',
                                'confidence' => null,
                                'status' => 'active',
                                'created_by' => $user->id,
                                'updated_by' => $user->id,
                            ],
                        );
                    }
                }
            }
        });

        return EventPersonGroup::query()
            ->forEvent($event->id)
            ->whereIn('slug', $groupSeeds->pluck('key')->all())
            ->orderByDesc('importance_rank')
            ->orderBy('display_name')
            ->get();
    }

    /**
     * @param  array<int, string>  $memberRoleKeys
     * @param  \Illuminate\Support\Collection<string, array<string, mixed>>  $peopleByRole
     */
    private function resolveSide(array $memberRoleKeys, \Illuminate\Support\Collection $peopleByRole): string
    {
        $sides = collect($memberRoleKeys)
            ->map(fn (string $roleKey): ?string => $peopleByRole->get($roleKey)['side'] ?? null)
            ->filter(fn (?string $side): bool => filled($side) && $side !== 'neutral')
            ->unique()
            ->values();

        if ($sides->count() === 1) {
            return (string) $sides->first();
        }

        return 'neutral';
    }
}
