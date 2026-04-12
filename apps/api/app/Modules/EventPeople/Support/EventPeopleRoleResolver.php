<?php

namespace App\Modules\EventPeople\Support;

use App\Modules\EventPeople\Models\EventPerson;
use App\Modules\EventPeople\Services\EventPeoplePresetCatalog;
use App\Modules\Events\Models\Event;
use Illuminate\Support\Collection;

class EventPeopleRoleResolver
{
    public function __construct(
        private readonly EventPeoplePresetCatalog $presetCatalog,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function presetPackageForEvent(Event $event): array
    {
        return $this->presetCatalog->forEventType($event->event_type?->value ?? $event->event_type);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function presetPeopleForEvent(Event $event): Collection
    {
        return collect($this->presetPackageForEvent($event)['people'] ?? [])->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $presetPeople
     * @return array{role_key: ?string, role_label: string, role_family: string}
     */
    public function resolveRoleMeta(EventPerson $person, Collection $presetPeople): array
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

    /**
     * @param  Collection<int, EventPerson>  $people
     * @param  Collection<int, array<string, mixed>>  $presetPeople
     * @param  array<int, string>  $roleKeys
     * @return Collection<int, EventPerson>
     */
    public function matchPeopleByRoleKeys(Collection $people, Collection $presetPeople, array $roleKeys): Collection
    {
        return $people
            ->map(function (EventPerson $person) use ($presetPeople): array {
                return [
                    'person' => $person,
                    'role_meta' => $this->resolveRoleMeta($person, $presetPeople),
                ];
            })
            ->filter(fn (array $entry): bool => in_array($entry['role_meta']['role_key'], $roleKeys, true))
            ->sortByDesc(fn (array $entry): int => (int) $entry['person']->importance_rank)
            ->map(fn (array $entry): EventPerson => $entry['person'])
            ->values();
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
