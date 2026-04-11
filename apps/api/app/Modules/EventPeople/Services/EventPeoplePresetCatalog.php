<?php

namespace App\Modules\EventPeople\Services;

use App\Modules\Events\Enums\EventType;

class EventPeoplePresetCatalog
{
    /**
     * @return array<string, mixed>
     */
    public function forEventType(?string $eventType): array
    {
        return match ($eventType) {
            EventType::Wedding->value => $this->wedding(),
            EventType::Corporate->value, EventType::Fair->value => $this->corporate(),
            EventType::Birthday->value, EventType::Fifteen->value, EventType::Graduation->value => $this->social(),
            default => $this->generic(),
        };
    }

    private function wedding(): array
    {
        return [
            'people' => [
                ['key' => 'bride', 'label' => 'Noiva', 'type' => 'bride', 'side' => 'neutral', 'importance_rank' => 100],
                ['key' => 'groom', 'label' => 'Noivo', 'type' => 'groom', 'side' => 'neutral', 'importance_rank' => 100],
                ['key' => 'mother_bride', 'label' => 'Mae da noiva', 'type' => 'mother', 'side' => 'bride_side', 'importance_rank' => 90],
                ['key' => 'father_bride', 'label' => 'Pai da noiva', 'type' => 'father', 'side' => 'bride_side', 'importance_rank' => 90],
                ['key' => 'mother_groom', 'label' => 'Mae do noivo', 'type' => 'mother', 'side' => 'groom_side', 'importance_rank' => 90],
                ['key' => 'father_groom', 'label' => 'Pai do noivo', 'type' => 'father', 'side' => 'groom_side', 'importance_rank' => 90],
                ['key' => 'bridesmaid', 'label' => 'Madrinha', 'type' => 'bridesmaid', 'side' => 'bride_side', 'importance_rank' => 70],
                ['key' => 'groomsman', 'label' => 'Padrinho', 'type' => 'groomsman', 'side' => 'groom_side', 'importance_rank' => 70],
                ['key' => 'photographer', 'label' => 'Fotografo', 'type' => 'vendor', 'side' => 'neutral', 'importance_rank' => 60],
                ['key' => 'ceremonialist', 'label' => 'Cerimonialista', 'type' => 'vendor', 'side' => 'neutral', 'importance_rank' => 60],
            ],
            'relations' => [
                ['type' => 'spouse_of', 'label' => 'Conjuge de', 'directionality' => 'undirected'],
                ['type' => 'mother_of', 'label' => 'Mae de', 'directionality' => 'directed'],
                ['type' => 'father_of', 'label' => 'Pai de', 'directionality' => 'directed'],
                ['type' => 'sibling_of', 'label' => 'Irmao de', 'directionality' => 'undirected'],
                ['type' => 'friend_of', 'label' => 'Amigo de', 'directionality' => 'undirected'],
                ['type' => 'photographer_of_event', 'label' => 'Fotografo do evento', 'directionality' => 'directed'],
                ['type' => 'ceremonialist_of_event', 'label' => 'Cerimonialista do evento', 'directionality' => 'directed'],
            ],
        ];
    }

    private function corporate(): array
    {
        return [
            'people' => [
                ['key' => 'host', 'label' => 'Host', 'type' => 'executive', 'side' => 'host_side', 'importance_rank' => 100],
                ['key' => 'speaker', 'label' => 'Palestrante', 'type' => 'speaker', 'side' => 'neutral', 'importance_rank' => 90],
                ['key' => 'executive', 'label' => 'Executivo', 'type' => 'executive', 'side' => 'company_side', 'importance_rank' => 80],
                ['key' => 'staff', 'label' => 'Equipe', 'type' => 'staff', 'side' => 'company_side', 'importance_rank' => 60],
                ['key' => 'vendor', 'label' => 'Fornecedor', 'type' => 'vendor', 'side' => 'neutral', 'importance_rank' => 50],
            ],
            'relations' => [
                ['type' => 'manager_of', 'label' => 'Gestor de', 'directionality' => 'directed'],
                ['type' => 'teammate_of', 'label' => 'Colega de equipe', 'directionality' => 'undirected'],
                ['type' => 'works_with', 'label' => 'Trabalha com', 'directionality' => 'undirected'],
                ['type' => 'speaker_with', 'label' => 'Participa com', 'directionality' => 'undirected'],
                ['type' => 'sponsor_of', 'label' => 'Patrocinador de', 'directionality' => 'directed'],
            ],
        ];
    }

    private function social(): array
    {
        return [
            'people' => [
                ['key' => 'main_host', 'label' => 'Pessoa principal', 'type' => 'guest', 'side' => 'neutral', 'importance_rank' => 100],
                ['key' => 'mother', 'label' => 'Mae', 'type' => 'mother', 'side' => 'neutral', 'importance_rank' => 90],
                ['key' => 'father', 'label' => 'Pai', 'type' => 'father', 'side' => 'neutral', 'importance_rank' => 90],
                ['key' => 'friend', 'label' => 'Amigo(a)', 'type' => 'friend', 'side' => 'neutral', 'importance_rank' => 70],
                ['key' => 'vendor', 'label' => 'Fornecedor', 'type' => 'vendor', 'side' => 'neutral', 'importance_rank' => 50],
            ],
            'relations' => [
                ['type' => 'mother_of', 'label' => 'Mae de', 'directionality' => 'directed'],
                ['type' => 'father_of', 'label' => 'Pai de', 'directionality' => 'directed'],
                ['type' => 'sibling_of', 'label' => 'Irmao de', 'directionality' => 'undirected'],
                ['type' => 'friend_of', 'label' => 'Amigo de', 'directionality' => 'undirected'],
                ['type' => 'vendor_of_event', 'label' => 'Fornecedor do evento', 'directionality' => 'directed'],
            ],
        ];
    }

    private function generic(): array
    {
        return [
            'people' => [
                ['key' => 'guest', 'label' => 'Pessoa do evento', 'type' => 'guest', 'side' => 'neutral', 'importance_rank' => 50],
                ['key' => 'friend', 'label' => 'Amigo(a)', 'type' => 'friend', 'side' => 'neutral', 'importance_rank' => 50],
                ['key' => 'vendor', 'label' => 'Fornecedor', 'type' => 'vendor', 'side' => 'neutral', 'importance_rank' => 50],
            ],
            'relations' => [
                ['type' => 'friend_of', 'label' => 'Amigo de', 'directionality' => 'undirected'],
                ['type' => 'works_with', 'label' => 'Trabalha com', 'directionality' => 'undirected'],
                ['type' => 'vendor_of_event', 'label' => 'Fornecedor do evento', 'directionality' => 'directed'],
            ],
        ];
    }
}
