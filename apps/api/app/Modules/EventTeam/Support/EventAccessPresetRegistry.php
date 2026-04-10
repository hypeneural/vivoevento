<?php

namespace App\Modules\EventTeam\Support;

class EventAccessPresetRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function eventPresets(): array
    {
        return [
            'event.manager' => [
                'key' => 'event.manager',
                'scope' => 'event',
                'persisted_role' => 'manager',
                'label' => 'Gerenciar evento',
                'description' => 'Gerencia equipe, mídias, moderação e operação do evento.',
                'capabilities' => ['overview', 'media', 'moderation', 'wall', 'play', 'team'],
                'permissions' => [
                    'events.view',
                    'events.update',
                    'events.manage_team',
                    'media.view',
                    'media.moderate',
                    'gallery.view',
                    'wall.view',
                    'wall.manage',
                    'play.view',
                    'play.manage',
                    'hub.view',
                ],
            ],
            'event.operator' => [
                'key' => 'event.operator',
                'scope' => 'event',
                'persisted_role' => 'operator',
                'label' => 'Operar evento',
                'description' => 'Opera telão e jogos, além de visualizar e moderar mídias do evento.',
                'capabilities' => ['overview', 'media', 'moderation', 'wall', 'play'],
                'permissions' => [
                    'events.view',
                    'media.view',
                    'media.moderate',
                    'gallery.view',
                    'wall.view',
                    'wall.manage',
                    'play.view',
                    'play.manage',
                ],
            ],
            'event.moderator' => [
                'key' => 'event.moderator',
                'scope' => 'event',
                'persisted_role' => 'moderator',
                'label' => 'Moderar mídias',
                'description' => 'Visualiza e modera mídias do evento sem operar telão ou jogos.',
                'capabilities' => ['overview', 'media', 'moderation'],
                'permissions' => [
                    'events.view',
                    'media.view',
                    'media.moderate',
                    'gallery.view',
                ],
            ],
            'event.media-viewer' => [
                'key' => 'event.media-viewer',
                'scope' => 'event',
                'persisted_role' => 'viewer',
                'label' => 'Ver mídias',
                'description' => 'Apenas visualiza as mídias e o resumo do evento.',
                'capabilities' => ['overview', 'media'],
                'permissions' => [
                    'events.view',
                    'media.view',
                    'gallery.view',
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function organizationPresets(): array
    {
        return [
            'organization.manager' => [
                'key' => 'organization.manager',
                'scope' => 'organization',
                'persisted_role' => 'partner-manager',
                'label' => 'Gerente / Secretaria',
                'description' => 'Acompanha a operação da organização e ajuda na gestão do dia a dia.',
                'capabilities' => ['dashboard', 'events', 'media', 'moderation', 'gallery', 'wall', 'play', 'hub', 'team'],
            ],
            'organization.finance' => [
                'key' => 'organization.finance',
                'scope' => 'organization',
                'persisted_role' => 'financeiro',
                'label' => 'Financeiro',
                'description' => 'Visualiza cobranças, planos e indicadores financeiros.',
                'capabilities' => ['billing', 'plans', 'analytics'],
            ],
            'organization.viewer' => [
                'key' => 'organization.viewer',
                'scope' => 'organization',
                'persisted_role' => 'viewer',
                'label' => 'Leitura',
                'description' => 'Acompanha os dados principais sem ações operacionais sensíveis.',
                'capabilities' => ['dashboard', 'events', 'gallery'],
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function eventPresetKeys(): array
    {
        return array_keys($this->eventPresets());
    }

    /**
     * @return array<string, mixed>
     */
    public function presetByKey(string $key): array
    {
        return $this->eventPresets()[$key] ?? $this->eventPresets()['event.media-viewer'];
    }

    public function persistedRoleForPresetKey(string $key): string
    {
        return (string) ($this->presetByKey($key)['persisted_role'] ?? 'viewer');
    }

    /**
     * @return array<string, mixed>
     */
    public function presetForPersistedRole(string $role): array
    {
        foreach ($this->eventPresets() as $preset) {
            if (($preset['persisted_role'] ?? null) === $role) {
                return $preset;
            }
        }

        return $this->eventPresets()['event.media-viewer'];
    }

    /**
     * @return array<int, string>
     */
    public function permissionsForPersistedRole(string $role): array
    {
        return $this->presetForPersistedRole($role)['permissions'] ?? [];
    }
}
