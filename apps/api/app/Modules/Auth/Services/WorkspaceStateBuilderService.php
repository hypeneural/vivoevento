<?php

namespace App\Modules\Auth\Services;

use App\Modules\EventTeam\Support\EventAccessPresetRegistry;
use App\Modules\Users\Models\User;

class WorkspaceStateBuilderService
{
    public function __construct(
        private readonly EventAccessPresetRegistry $presetRegistry,
    ) {}

    /**
     * @return array{
     *   active_context: array<string, mixed>|null,
     *   organizations: array<int, array<string, mixed>>,
     *   event_accesses: array<int, array<string, mixed>>
     * }
     */
    public function build(User $user): array
    {
        $organizations = $this->buildOrganizationWorkspaces($user);
        $eventAccesses = $this->buildEventAccesses($user);
        $activeContext = $this->resolveActiveContext($user, $organizations, $eventAccesses);

        return [
            'active_context' => $activeContext,
            'organizations' => $organizations,
            'event_accesses' => $eventAccesses,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildOrganizationWorkspaces(User $user): array
    {
        return $user->organizationMembers()
            ->active()
            ->with('organization:id,uuid,trade_name,legal_name,slug,status,type')
            ->get()
            ->map(function ($membership) {
                $organization = $membership->organization;

                if (! $organization) {
                    return null;
                }

                return [
                    'organization_id' => $organization->id,
                    'organization_uuid' => $organization->uuid,
                    'organization_name' => $organization->displayName(),
                    'organization_slug' => $organization->slug,
                    'organization_type' => $organization->type?->value,
                    'organization_status' => $organization->status?->value,
                    'role_key' => $membership->role_key,
                    'role_label' => $this->organizationRoleLabel((string) $membership->role_key),
                    'is_owner' => (bool) $membership->is_owner,
                    'entry_path' => '/',
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildEventAccesses(User $user): array
    {
        return $user->eventTeamMembers()
            ->with('event.organization:id,uuid,trade_name,legal_name,slug,status,type')
            ->get()
            ->map(function ($membership) {
                $event = $membership->event;
                $organization = $event?->organization;

                if (! $event || ! $organization) {
                    return null;
                }

                $preset = $this->presetRegistry->presetForPersistedRole((string) $membership->role);

                return [
                    'event_id' => $event->id,
                    'event_uuid' => $event->uuid,
                    'event_title' => $event->title,
                    'event_slug' => $event->slug,
                    'event_date' => $event->starts_at?->toDateString(),
                    'event_status' => $event->status?->value,
                    'organization_id' => $organization->id,
                    'organization_name' => $organization->displayName(),
                    'organization_slug' => $organization->slug,
                    'role_key' => $preset['key'],
                    'role_label' => $preset['label'],
                    'persisted_role' => $preset['persisted_role'],
                    'capabilities' => $preset['capabilities'],
                    'entry_path' => "/my-events/{$event->id}",
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $organizations
     * @param  array<int, array<string, mixed>>  $eventAccesses
     * @return array<string, mixed>|null
     */
    private function resolveActiveContext(User $user, array $organizations, array $eventAccesses): ?array
    {
        $stored = data_get($user->preferences, 'active_context');

        if (is_array($stored)) {
            if (($stored['type'] ?? null) === 'organization') {
                $match = collect($organizations)->firstWhere('organization_id', $stored['organization_id'] ?? null);

                if ($match) {
                    return [
                        'type' => 'organization',
                        'organization_id' => $match['organization_id'],
                        'event_id' => null,
                        'role_key' => $match['role_key'],
                        'role_label' => $match['role_label'],
                        'capabilities' => [],
                        'entry_path' => '/',
                    ];
                }
            }

            if (($stored['type'] ?? null) === 'event') {
                $match = collect($eventAccesses)->firstWhere('event_id', $stored['event_id'] ?? null);

                if ($match) {
                    return [
                        'type' => 'event',
                        'organization_id' => $match['organization_id'],
                        'event_id' => $match['event_id'],
                        'role_key' => $match['role_key'],
                        'role_label' => $match['role_label'],
                        'capabilities' => $match['capabilities'],
                        'entry_path' => $match['entry_path'],
                    ];
                }
            }
        }

        if ($organizations !== []) {
            $first = $organizations[0];

            return [
                'type' => 'organization',
                'organization_id' => $first['organization_id'],
                'event_id' => null,
                'role_key' => $first['role_key'],
                'role_label' => $first['role_label'],
                'capabilities' => [],
                'entry_path' => '/',
            ];
        }

        if ($eventAccesses !== []) {
            $first = $eventAccesses[0];

            return [
                'type' => 'event',
                'organization_id' => $first['organization_id'],
                'event_id' => $first['event_id'],
                'role_key' => $first['role_key'],
                'role_label' => $first['role_label'],
                'capabilities' => $first['capabilities'],
                'entry_path' => $first['entry_path'],
            ];
        }

        return null;
    }

    private function organizationRoleLabel(string $roleKey): string
    {
        return match ($roleKey) {
            'partner-owner' => 'Proprietária',
            'partner-manager' => 'Gerente / Secretaria',
            'financeiro' => 'Financeiro',
            'event-operator' => 'Operador de evento',
            'viewer' => 'Leitura',
            default => ucfirst(str_replace(['-', '_'], ' ', $roleKey)),
        };
    }
}
