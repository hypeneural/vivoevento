<?php

namespace App\Modules\EventPeople\Queries;

use App\Modules\EventPeople\Models\EventPerson;
use App\Modules\EventPeople\Models\EventPersonPairScore;
use App\Modules\EventPeople\Models\EventPersonRelation;
use App\Modules\EventPeople\Services\EventPeoplePresetCatalog;
use App\Modules\Events\Models\Event;
use App\Modules\MediaProcessing\Services\MediaAssetUrlService;
use Illuminate\Support\Collection;

class BuildEventPeopleGraphQuery
{
    public function __construct(
        private readonly EventPeoplePresetCatalog $presetCatalog,
        private readonly ListEventPeopleQuery $peopleQuery,
        private readonly MediaAssetUrlService $assets,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function build(Event $event, array $filters = []): array
    {
        $eventType = is_string($event->event_type)
            ? $event->event_type
            : $event->event_type?->value;
        $presetPackage = $this->presetCatalog->forEventType($eventType);
        $presetPeople = collect($presetPackage['people'] ?? [])->values();

        /** @var Collection<int, EventPerson> $people */
        $people = $this->peopleQuery
            ->build($event, $filters)
            ->with([
                'avatarMedia.variants',
                'primaryReferencePhoto.media.variants',
                'primaryReferencePhoto.uploadMedia.variants',
            ])
            ->get();

        $peopleIds = $people->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();

        /** @var Collection<int, EventPersonRelation> $relations */
        $relations = EventPersonRelation::query()
            ->where('event_id', $event->id)
            ->whereIn('person_a_id', $peopleIds)
            ->whereIn('person_b_id', $peopleIds)
            ->with(['personA', 'personB'])
            ->orderByDesc('is_primary')
            ->orderByDesc('strength')
            ->orderBy('id')
            ->get();

        $pairScores = EventPersonPairScore::query()
            ->where('event_id', $event->id)
            ->whereIn('person_a_id', $peopleIds)
            ->whereIn('person_b_id', $peopleIds)
            ->get()
            ->keyBy('person_pair_key');

        $peoplePayload = $people
            ->map(fn (EventPerson $person): array => $this->personPayload($person, $presetPeople))
            ->values();

        $relationsPayload = $relations
            ->map(fn (EventPersonRelation $relation): array => $this->relationPayload($relation, $pairScores))
            ->values();

        $peopleById = $peoplePayload->keyBy('id');
        $groups = collect($presetPackage['groups'] ?? [])
            ->map(function (array $group) use ($peopleById): array {
                $memberCount = $peopleById
                    ->filter(fn (array $person): bool => in_array($person['role_key'], $group['member_role_keys'] ?? [], true))
                    ->count();

                return [
                    'key' => $group['key'],
                    'label' => $group['label'],
                    'role_family' => $group['role_family'],
                    'member_role_keys' => array_values($group['member_role_keys'] ?? []),
                    'importance_rank' => (int) ($group['importance_rank'] ?? 0),
                    'current_member_count' => $memberCount,
                ];
            })
            ->values();

        $connectedPeopleCount = $relationsPayload
            ->flatMap(fn (array $relation): array => [$relation['person_a_id'], $relation['person_b_id']])
            ->unique()
            ->count();

        return [
            'people' => $peoplePayload->all(),
            'relations' => $relationsPayload->all(),
            'groups' => $groups->all(),
            'stats' => [
                'people_count' => $peoplePayload->count(),
                'relation_count' => $relationsPayload->count(),
                'connected_people_count' => $connectedPeopleCount,
                'principal_people_count' => $peoplePayload->where('role_family', 'principal')->count(),
                'without_primary_photo_count' => $peoplePayload->where('has_primary_photo', false)->count(),
            ],
            'filters' => [
                'statuses' => $peoplePayload->pluck('status')->filter()->unique()->values()->all(),
                'sides' => $peoplePayload->pluck('side')->filter()->unique()->values()->all(),
                'role_families' => $peoplePayload->pluck('role_family')->filter()->unique()->values()->all(),
                'relation_types' => $relationsPayload->pluck('relation_type')->filter()->unique()->values()->all(),
            ],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $presetPeople
     * @return array<string, mixed>
     */
    private function personPayload(EventPerson $person, Collection $presetPeople): array
    {
        $stat = $person->relationLoaded('mediaStats') ? $person->mediaStats->first() : null;
        $roleMeta = $this->resolveRoleMeta($person, $presetPeople);

        return [
            'id' => (int) $person->id,
            'display_name' => $person->display_name,
            'role_key' => $roleMeta['role_key'],
            'role_label' => $roleMeta['role_label'],
            'role_family' => $roleMeta['role_family'],
            'type' => $person->type?->value ?? $person->type,
            'side' => $person->side?->value ?? $person->side,
            'status' => $person->status?->value ?? $person->status,
            'avatar_url' => $this->resolveAvatarUrl($person),
            'importance_rank' => (int) $person->importance_rank,
            'media_count' => (int) ($stat?->media_count ?? 0),
            'published_media_count' => (int) ($stat?->published_media_count ?? 0),
            'has_primary_photo' => (bool) ($person->primary_reference_photo_id || $stat?->best_media_id || $stat?->latest_media_id),
        ];
    }

    /**
     * @param  Collection<int, EventPersonPairScore>  $pairScores
     * @return array<string, mixed>
     */
    private function relationPayload(EventPersonRelation $relation, Collection $pairScores): array
    {
        $pairScore = $pairScores->get($relation->person_pair_key);

        return [
            'id' => (int) $relation->id,
            'person_a_id' => (int) $relation->person_a_id,
            'person_b_id' => (int) $relation->person_b_id,
            'person_a_name' => $relation->personA?->display_name,
            'person_b_name' => $relation->personB?->display_name,
            'relation_type' => $relation->relation_type?->value ?? $relation->relation_type,
            'directionality' => $relation->directionality?->value ?? $relation->directionality,
            'source' => $relation->source?->value ?? $relation->source,
            'strength' => $relation->strength,
            'is_primary' => (bool) $relation->is_primary,
            'notes' => $relation->notes,
            'co_photo_count' => $pairScore?->co_media_count,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $presetPeople
     * @return array{role_key: ?string, role_label: string, role_family: string}
     */
    private function resolveRoleMeta(EventPerson $person, Collection $presetPeople): array
    {
        $personType = $person->type?->value ?? $person->type;
        $personSide = $person->side?->value ?? $person->side;
        $normalizedDisplayName = mb_strtolower(trim($person->display_name));

        $displayMatches = $presetPeople
            ->filter(function (array $preset) use ($normalizedDisplayName): bool {
                $labels = array_filter([
                    $preset['role_label'] ?? null,
                    $preset['label'] ?? null,
                ]);

                return collect($labels)
                    ->contains(fn (string $label): bool => mb_strtolower(trim($label)) === $normalizedDisplayName);
            })
            ->values();

        if ($displayMatches->count() === 1) {
            $match = $displayMatches->first();

            return [
                'role_key' => $match['role_key'] ?? $match['key'] ?? null,
                'role_label' => $match['role_label'] ?? $match['label'] ?? $this->formatTypeLabel($personType),
                'role_family' => $match['role_family'] ?? $this->fallbackRoleFamily($personType),
            ];
        }

        $typeSideMatches = $presetPeople
            ->filter(fn (array $preset): bool => ($preset['type'] ?? null) === $personType && ($preset['side'] ?? null) === $personSide)
            ->values();

        if ($typeSideMatches->count() === 1) {
            $match = $typeSideMatches->first();

            return [
                'role_key' => $match['role_key'] ?? $match['key'] ?? null,
                'role_label' => $match['role_label'] ?? $match['label'] ?? $this->formatTypeLabel($personType),
                'role_family' => $match['role_family'] ?? $this->fallbackRoleFamily($personType),
            ];
        }

        return [
            'role_key' => null,
            'role_label' => $this->formatTypeLabel($personType),
            'role_family' => $this->fallbackRoleFamily($personType),
        ];
    }

    private function resolveAvatarUrl(EventPerson $person): ?string
    {
        $primaryReferencePhoto = $person->relationLoaded('primaryReferencePhoto') ? $person->primaryReferencePhoto : null;

        if ($primaryReferencePhoto?->uploadMedia) {
            return $this->assets->preview($primaryReferencePhoto->uploadMedia)
                ?? $this->assets->original($primaryReferencePhoto->uploadMedia);
        }

        if ($primaryReferencePhoto?->media) {
            return $this->assets->preview($primaryReferencePhoto->media)
                ?? $this->assets->original($primaryReferencePhoto->media);
        }

        if ($person->relationLoaded('avatarMedia') && $person->avatarMedia) {
            return $this->assets->thumbnail($person->avatarMedia)
                ?? $this->assets->original($person->avatarMedia);
        }

        return null;
    }

    private function formatTypeLabel(?string $type): string
    {
        return match ($type) {
            'bride' => 'Noiva',
            'groom' => 'Noivo',
            'mother' => 'Mae',
            'father' => 'Pai',
            'sibling' => 'Irmao(irma)',
            'friend' => 'Amigo(a)',
            'groomsman' => 'Padrinho',
            'bridesmaid' => 'Madrinha',
            'vendor' => 'Fornecedor',
            'staff' => 'Equipe',
            'speaker' => 'Palestrante',
            'artist' => 'Artista',
            'executive' => 'Executivo',
            default => 'Pessoa do evento',
        };
    }

    private function fallbackRoleFamily(?string $type): string
    {
        return match ($type) {
            'bride', 'groom', 'executive' => 'principal',
            'mother', 'father', 'sibling' => 'familia',
            'groomsman', 'bridesmaid' => 'corte',
            'friend' => 'amigos',
            'staff' => 'equipe',
            'vendor', 'artist' => 'fornecedor',
            'speaker' => 'corporativo',
            default => 'outros',
        };
    }
}
