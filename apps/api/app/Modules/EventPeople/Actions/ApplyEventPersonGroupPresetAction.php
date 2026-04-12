<?php

namespace App\Modules\EventPeople\Actions;

use App\Modules\EventPeople\Models\EventPersonGroup;
use App\Modules\EventPeople\Services\EventPeoplePresetCatalog;
use App\Modules\Events\Models\Event;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ApplyEventPersonGroupPresetAction
{
    public function __construct(
        private readonly EventPeoplePresetCatalog $catalog,
    ) {}

    public function execute(Event $event, User $user): Collection
    {
        $preset = $this->catalog->forEventType($event->event_type?->value ?? $event->event_type);
        $groupSeeds = collect($preset['groups'] ?? []);
        $peopleByRole = collect($preset['people'] ?? [])->keyBy('role_key');

        DB::transaction(function () use ($event, $groupSeeds, $peopleByRole, $user): void {
            foreach ($groupSeeds as $seed) {
                EventPersonGroup::query()->firstOrCreate(
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
