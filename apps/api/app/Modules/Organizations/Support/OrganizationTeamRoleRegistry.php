<?php

namespace App\Modules\Organizations\Support;

class OrganizationTeamRoleRegistry
{
    /**
     * @return array<int, string>
     */
    public function allowedInvitationRoleKeys(): array
    {
        return [
            'partner-manager',
            'event-operator',
            'financeiro',
            'viewer',
        ];
    }

    public function labelForRoleKey(string $roleKey): string
    {
        return match ($roleKey) {
            'partner-manager' => 'Gerente / Secretaria',
            'event-operator' => 'Operar eventos',
            'financeiro' => 'Financeiro',
            'viewer' => 'Acompanhar em leitura',
            default => ucfirst(str_replace(['-', '_'], ' ', $roleKey)),
        };
    }

    public function descriptionForRoleKey(string $roleKey): string
    {
        return match ($roleKey) {
            'partner-manager' => 'Pode organizar clientes, eventos e a rotina da operacao da empresa.',
            'event-operator' => 'Pode operar os eventos, acompanhar midias e atuar na execucao do dia.',
            'financeiro' => 'Pode acompanhar cobrancas, faturamento e dados financeiros da organizacao.',
            'viewer' => 'Pode acompanhar informacoes liberadas sem alterar a configuracao da conta.',
            default => 'Acesso configurado para a organizacao.',
        };
    }
}
