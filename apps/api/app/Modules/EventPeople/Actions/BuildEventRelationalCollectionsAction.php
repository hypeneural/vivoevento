<?php

namespace App\Modules\EventPeople\Actions;

use App\Modules\EventPeople\Enums\EventPersonAssignmentStatus;
use App\Modules\EventPeople\Enums\EventRelationalCollectionStatus;
use App\Modules\EventPeople\Enums\EventRelationalCollectionType;
use App\Modules\EventPeople\Enums\EventRelationalCollectionVisibility;
use App\Modules\EventPeople\Models\EventCoverageTarget;
use App\Modules\EventPeople\Models\EventPerson;
use App\Modules\EventPeople\Models\EventPersonGroup;
use App\Modules\EventPeople\Models\EventRelationalCollection;
use App\Modules\EventPeople\Models\EventRelationalCollectionItem;
use App\Modules\Events\Models\Event;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BuildEventRelationalCollectionsAction
{
    public function __construct(
        private readonly SyncEventCoverageTargetsAction $syncCoverageTargets,
    ) {}

    /**
     * @return EloquentCollection<int, EventRelationalCollection>
     */
    public function execute(Event $event, User $user): EloquentCollection
    {
        $this->syncCoverageTargets->execute($event, $user);

        $definitions = collect()
            ->merge($this->personDefinitions($event))
            ->merge($this->pairDefinitions($event))
            ->merge($this->groupDefinitions($event))
            ->merge($this->mustHaveDefinitions($event));

        $this->syncCollections($event, $definitions);

        return EventRelationalCollection::query()
            ->forEvent($event->id)
            ->with([
                'personA',
                'personB',
                'group',
                'items.media',
            ])
            ->orderByRaw("case when visibility = 'public_ready' then 0 else 1 end")
            ->orderByRaw("case
                when collection_type = 'must_have_delivery' then 0
                when collection_type = 'pair_best_of' then 1
                when collection_type = 'family_moment' then 2
                when collection_type = 'group_best_of' then 3
                else 4
            end")
            ->orderByDesc('generated_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function personDefinitions(Event $event): Collection
    {
        return EventPerson::query()
            ->forEvent($event->id)
            ->where('status', 'active')
            ->orderByDesc('importance_rank')
            ->orderBy('id')
            ->get()
            ->filter(fn (EventPerson $person): bool => (int) ($person->importance_rank ?? 0) >= 80)
            ->map(function (EventPerson $person) use ($event): ?array {
                $items = $this->selectMediaRows($event->id, [$person->id], false, 1);

                if ($items->isEmpty()) {
                    return null;
                }

                return [
                    'collection_key' => "person-best-of:{$person->id}",
                    'collection_type' => EventRelationalCollectionType::PersonBestOf->value,
                    'source_type' => 'person',
                    'person_a_id' => $person->id,
                    'person_b_id' => null,
                    'event_person_group_id' => null,
                    'display_name' => "Melhores de {$person->display_name}",
                    'status' => EventRelationalCollectionStatus::Active->value,
                    'visibility' => EventRelationalCollectionVisibility::Internal->value,
                    'metadata' => [
                        'role_label' => $person->type?->value ?? $person->type,
                    ],
                    'published_at' => null,
                    'items' => $items->all(),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function pairDefinitions(Event $event): Collection
    {
        return \App\Modules\EventPeople\Models\EventPersonRelation::query()
            ->where('event_id', $event->id)
            ->where('is_primary', true)
            ->with(['personA', 'personB'])
            ->orderByDesc('strength')
            ->orderBy('id')
            ->get()
            ->map(function (\App\Modules\EventPeople\Models\EventPersonRelation $relation) use ($event): ?array {
                if (! $relation->personA || ! $relation->personB) {
                    return null;
                }

                $items = $this->selectMediaRows($event->id, [$relation->person_a_id, $relation->person_b_id], false, 2);

                if ($items->isEmpty()) {
                    return null;
                }

                return [
                    'collection_key' => "pair-best-of:{$relation->person_pair_key}",
                    'collection_type' => EventRelationalCollectionType::PairBestOf->value,
                    'source_type' => 'relation',
                    'person_a_id' => $relation->person_a_id,
                    'person_b_id' => $relation->person_b_id,
                    'event_person_group_id' => null,
                    'display_name' => "{$relation->personA->display_name} + {$relation->personB->display_name}",
                    'status' => EventRelationalCollectionStatus::Active->value,
                    'visibility' => EventRelationalCollectionVisibility::Internal->value,
                    'metadata' => [
                        'relation_type' => $relation->relation_type?->value ?? $relation->relation_type,
                        'person_pair_key' => $relation->person_pair_key,
                    ],
                    'published_at' => null,
                    'items' => $items->all(),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function groupDefinitions(Event $event): Collection
    {
        $groups = EventPersonGroup::query()
            ->forEvent($event->id)
            ->with('memberships')
            ->orderByDesc('importance_rank')
            ->orderBy('id')
            ->get();

        $definitions = collect();

        foreach ($groups as $group) {
            $memberIds = $group->memberships
                ->where('status', 'active')
                ->pluck('event_person_id')
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all();

            $minimumDistinctPeople = max(1, min(2, count($memberIds)));
            $items = $this->selectMediaRows($event->id, $memberIds, false, $minimumDistinctPeople);

            if ($items->isEmpty()) {
                continue;
            }

            $definitions->push([
                'collection_key' => "group-best-of:{$group->id}",
                'collection_type' => EventRelationalCollectionType::GroupBestOf->value,
                'source_type' => 'group',
                'person_a_id' => null,
                'person_b_id' => null,
                'event_person_group_id' => $group->id,
                'display_name' => $group->display_name,
                'status' => EventRelationalCollectionStatus::Active->value,
                'visibility' => EventRelationalCollectionVisibility::Internal->value,
                'metadata' => [
                    'group_type' => $group->group_type,
                    'member_count' => count($memberIds),
                ],
                'published_at' => null,
                'items' => $items->all(),
            ]);

            if ($this->isFamilyGroup($group)) {
                $definitions->push([
                    'collection_key' => "family-moment:{$group->id}",
                    'collection_type' => EventRelationalCollectionType::FamilyMoment->value,
                    'source_type' => 'group',
                    'person_a_id' => null,
                    'person_b_id' => null,
                    'event_person_group_id' => $group->id,
                    'display_name' => "Momentos de {$group->display_name}",
                    'status' => EventRelationalCollectionStatus::Active->value,
                    'visibility' => EventRelationalCollectionVisibility::Internal->value,
                    'metadata' => [
                        'group_type' => $group->group_type,
                        'member_count' => count($memberIds),
                    ],
                    'published_at' => null,
                    'items' => $items->all(),
                ]);
            }
        }

        return $definitions;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function mustHaveDefinitions(Event $event): Collection
    {
        return EventCoverageTarget::query()
            ->forEvent($event->id)
            ->with(['personA', 'personB', 'group.memberships'])
            ->orderByDesc('importance_rank')
            ->orderBy('id')
            ->get()
            ->map(function (EventCoverageTarget $target) use ($event): ?array {
                $source = $this->resolveTargetSource($target);
                $memberIds = $source['person_ids'];

                if ($memberIds === []) {
                    return null;
                }

                $items = $this->selectMediaRows(
                    $event->id,
                    $memberIds,
                    true,
                    $source['minimum_distinct_people'],
                );

                if ($items->isEmpty()) {
                    return null;
                }

                return [
                    'collection_key' => "must-have:{$target->key}",
                    'collection_type' => EventRelationalCollectionType::MustHaveDelivery->value,
                    'source_type' => 'coverage_target',
                    'person_a_id' => $target->person_a_id,
                    'person_b_id' => $target->person_b_id,
                    'event_person_group_id' => $target->event_person_group_id,
                    'display_name' => $target->label,
                    'status' => EventRelationalCollectionStatus::Active->value,
                    'visibility' => EventRelationalCollectionVisibility::PublicReady->value,
                    'metadata' => [
                        'target_key' => $target->key,
                        'target_type' => $target->target_type?->value ?? $target->target_type,
                    ],
                    'published_at' => now(),
                    'items' => $items->all(),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $definitions
     */
    private function syncCollections(Event $event, Collection $definitions): void
    {
        DB::transaction(function () use ($definitions, $event): void {
            $keys = [];

            foreach ($definitions as $definition) {
                $keys[] = $definition['collection_key'];

                $collection = EventRelationalCollection::query()->firstOrNew([
                    'event_id' => $event->id,
                    'collection_key' => $definition['collection_key'],
                ]);

                $shareToken = $definition['visibility'] === EventRelationalCollectionVisibility::PublicReady->value
                    ? ($collection->share_token ?: Str::random(32))
                    : null;

                $collection->fill([
                    'collection_type' => $definition['collection_type'],
                    'source_type' => $definition['source_type'],
                    'person_a_id' => $definition['person_a_id'],
                    'person_b_id' => $definition['person_b_id'],
                    'event_person_group_id' => $definition['event_person_group_id'],
                    'display_name' => $definition['display_name'],
                    'status' => $definition['status'],
                    'visibility' => $definition['visibility'],
                    'share_token' => $shareToken,
                    'metadata' => $definition['metadata'],
                    'generated_at' => now(),
                    'published_at' => $definition['published_at'],
                ]);
                $collection->save();

                $this->syncItems($collection, collect($definition['items']));
            }

            $staleCollections = EventRelationalCollection::query()
                ->forEvent($event->id)
                ->when($keys !== [], fn ($query) => $query->whereNotIn('collection_key', $keys))
                ->when($keys === [], fn ($query) => $query)
                ->get();

            foreach ($staleCollections as $collection) {
                $collection->delete();
            }
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     */
    private function syncItems(EventRelationalCollection $collection, Collection $items): void
    {
        $mediaIds = [];

        foreach ($items->values() as $index => $item) {
            $mediaIds[] = (int) $item['event_media_id'];

            EventRelationalCollectionItem::query()->updateOrCreate(
                [
                    'event_relational_collection_id' => $collection->id,
                    'event_media_id' => (int) $item['event_media_id'],
                ],
                [
                    'event_id' => $collection->event_id,
                    'sort_order' => $index,
                    'match_score' => $item['match_score'],
                    'matched_people_count' => $item['matched_people_count'],
                    'is_published' => $item['is_published'],
                    'metadata' => $item['metadata'] ?? null,
                ],
            );
        }

        EventRelationalCollectionItem::query()
            ->where('event_relational_collection_id', $collection->id)
            ->when($mediaIds !== [], fn ($query) => $query->whereNotIn('event_media_id', $mediaIds))
            ->when($mediaIds === [], fn ($query) => $query)
            ->delete();
    }

    /**
     * @param  array<int, int>  $personIds
     * @return Collection<int, array<string, mixed>>
     */
    private function selectMediaRows(
        int $eventId,
        array $personIds,
        bool $publishedOnly,
        int $minimumDistinctPeople,
        int $limit = 6,
    ): Collection {
        if ($personIds === []) {
            return collect();
        }

        $rows = DB::table('event_person_face_assignments as assignments')
            ->join('event_media_faces as faces', 'faces.id', '=', 'assignments.event_media_face_id')
            ->join('event_media as media', 'media.id', '=', 'faces.event_media_id')
            ->where('assignments.event_id', $eventId)
            ->where('assignments.status', EventPersonAssignmentStatus::Confirmed->value)
            ->whereIn('assignments.event_person_id', $personIds)
            ->where('media.moderation_status', ModerationStatus::Approved->value)
            ->when(
                $publishedOnly,
                fn ($query) => $query->where('media.publication_status', PublicationStatus::Published->value),
            )
            ->whereNull('media.deleted_at')
            ->groupBy('media.id', 'media.publication_status', 'media.created_at')
            ->havingRaw('COUNT(DISTINCT assignments.event_person_id) >= ?', [$minimumDistinctPeople])
            ->select([
                'media.id as event_media_id',
                'media.publication_status',
                'media.created_at',
            ])
            ->selectRaw('COUNT(DISTINCT assignments.event_person_id) as matched_people_count')
            ->get();

        return $rows
            ->sortByDesc(function ($row): array {
                $isPublished = (string) ($row->publication_status ?? '') === PublicationStatus::Published->value;
                $createdAtEpoch = strtotime((string) ($row->created_at ?? '')) ?: 0;

                return [
                    (int) ($row->matched_people_count ?? 0),
                    $isPublished ? 1 : 0,
                    $createdAtEpoch,
                ];
            })
            ->take($limit)
            ->values()
            ->map(function ($row, int $index) use ($limit): array {
            $matchedPeopleCount = (int) ($row->matched_people_count ?? 0);
            $isPublished = (string) ($row->publication_status ?? '') === PublicationStatus::Published->value;
            $createdAtEpoch = strtotime((string) ($row->created_at ?? '')) ?: 0;

            return [
                'event_media_id' => (int) $row->event_media_id,
                'matched_people_count' => $matchedPeopleCount,
                'is_published' => $isPublished,
                'match_score' => round(($matchedPeopleCount * 50) + ($isPublished ? 25 : 0) + max(0, $limit - $index), 2),
                'metadata' => [
                    'created_at_epoch' => $createdAtEpoch,
                ],
            ];
            });
    }

    /**
     * @return array{person_ids: array<int, int>, minimum_distinct_people: int}
     */
    private function resolveTargetSource(EventCoverageTarget $target): array
    {
        $targetType = $target->target_type?->value ?? $target->target_type;

        if ($targetType === 'person' && $target->person_a_id) {
            return [
                'person_ids' => [$target->person_a_id],
                'minimum_distinct_people' => 1,
            ];
        }

        if ($targetType === 'pair' && $target->person_a_id && $target->person_b_id) {
            return [
                'person_ids' => [$target->person_a_id, $target->person_b_id],
                'minimum_distinct_people' => 2,
            ];
        }

        $memberIds = $target->group?->memberships
            ?->where('status', 'active')
            ->pluck('event_person_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all() ?? [];

        return [
            'person_ids' => $memberIds,
            'minimum_distinct_people' => max(1, min(2, count($memberIds))),
        ];
    }

    private function isFamilyGroup(EventPersonGroup $group): bool
    {
        $groupType = Str::lower((string) ($group->group_type ?? ''));
        $slug = Str::lower((string) ($group->slug ?? ''));

        return str_contains($groupType, 'famil')
            || str_contains($slug, 'family')
            || str_contains($slug, 'famil');
    }
}
