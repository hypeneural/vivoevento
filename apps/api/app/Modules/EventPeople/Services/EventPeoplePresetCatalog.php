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
            EventType::Fifteen->value => $this->fifteen(),
            EventType::Birthday->value => $this->birthday(),
            EventType::Corporate->value => $this->corporate(),
            EventType::Fair->value => $this->fair(),
            EventType::Graduation->value => $this->graduation(),
            default => $this->generic(),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function wedding(): array
    {
        return $this->package('wedding', [
            $this->person('bride', 'Noiva', 'bride', 'neutral', 'principal', 100),
            $this->person('groom', 'Noivo', 'groom', 'neutral', 'principal', 100),
            $this->person('mother_bride', 'Mae da noiva', 'mother', 'bride_side', 'familia', 90),
            $this->person('father_bride', 'Pai da noiva', 'father', 'bride_side', 'familia', 90),
            $this->person('mother_groom', 'Mae do noivo', 'mother', 'groom_side', 'familia', 90),
            $this->person('father_groom', 'Pai do noivo', 'father', 'groom_side', 'familia', 90),
            $this->person('sibling', 'Irmao(irma)', 'sibling', 'neutral', 'familia', 75),
            $this->person('bridesmaid', 'Madrinha', 'bridesmaid', 'bride_side', 'corte', 70),
            $this->person('groomsman', 'Padrinho', 'groomsman', 'groom_side', 'corte', 70),
            $this->person('flower_girl', 'Daminha', 'guest', 'neutral', 'corte', 65),
            $this->person('page_boy', 'Pajem', 'guest', 'neutral', 'corte', 65),
            $this->person('close_friend', 'Amigo(a) proximo(a)', 'friend', 'neutral', 'amigos', 60),
            $this->person('ceremonialist', 'Cerimonialista', 'vendor', 'neutral', 'fornecedor', 60),
            $this->person('photographer', 'Fotografo', 'vendor', 'neutral', 'fornecedor', 60),
            $this->person('band_dj', 'Banda ou DJ', 'artist', 'neutral', 'fornecedor', 55),
        ], [
            $this->relation('spouse_of', 'Conjuge de', 'undirected'),
            $this->relation('mother_of', 'Mae de', 'directed'),
            $this->relation('father_of', 'Pai de', 'directed'),
            $this->relation('sibling_of', 'Irmao de', 'undirected'),
            $this->relation('friend_of', 'Amigo de', 'undirected'),
            $this->relation('photographer_of_event', 'Fotografo do evento', 'directed'),
            $this->relation('ceremonialist_of_event', 'Cerimonialista do evento', 'directed'),
        ], [
            $this->group('couple', 'Casal', 'principal', ['bride', 'groom'], 100),
            $this->group('bride_family', 'Familia da noiva', 'familia', ['bride', 'mother_bride', 'father_bride', 'sibling'], 90),
            $this->group('groom_family', 'Familia do noivo', 'familia', ['groom', 'mother_groom', 'father_groom', 'sibling'], 90),
            $this->group('wedding_party', 'Padrinhos e madrinhas', 'corte', ['bridesmaid', 'groomsman', 'flower_girl', 'page_boy'], 80),
        ], [
            $this->coverage('couple_portrait', 'Casal junto', 'group', ['bride', 'groom'], 'couple', 100),
            $this->coverage('bride_family', 'Noiva com familia', 'group', ['bride', 'mother_bride', 'father_bride'], 'bride_family', 95),
            $this->coverage('groom_family', 'Noivo com familia', 'group', ['groom', 'mother_groom', 'father_groom'], 'groom_family', 95),
            $this->coverage('wedding_party', 'Corte e padrinhos', 'group', ['bridesmaid', 'groomsman'], 'wedding_party', 80),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function fifteen(): array
    {
        return $this->package('fifteen', [
            $this->person('debutante', 'Debutante', 'guest', 'host_side', 'principal', 100),
            $this->person('mae_da_debutante', 'Mae da debutante', 'mother', 'host_side', 'familia', 95),
            $this->person('pai_da_debutante', 'Pai da debutante', 'father', 'host_side', 'familia', 95),
            $this->person('irmao_irma', 'Irmao(irma)', 'sibling', 'host_side', 'familia', 75),
            $this->person('avos', 'Avos', 'guest', 'host_side', 'familia', 75),
            $this->person('madrinha', 'Madrinha', 'bridesmaid', 'host_side', 'corte', 75),
            $this->person('padrinho', 'Padrinho', 'groomsman', 'host_side', 'corte', 75),
            $this->person('melhor_amiga', 'Melhor amiga', 'friend', 'neutral', 'amigos', 70),
            $this->person('dama_de_honra', 'Dama de honra', 'bridesmaid', 'neutral', 'corte', 70),
            $this->person('amigos', 'Amigos', 'friend', 'neutral', 'amigos', 60),
            $this->person('cerimonialista', 'Cerimonialista', 'vendor', 'neutral', 'fornecedor', 55),
            $this->person('fotografo', 'Fotografo', 'vendor', 'neutral', 'fornecedor', 55),
            $this->person('dj_banda', 'DJ ou banda', 'artist', 'neutral', 'fornecedor', 50),
        ], $this->socialRelations(), [
            $this->group('debutante_family', 'Familia da debutante', 'familia', ['debutante', 'mae_da_debutante', 'pai_da_debutante', 'irmao_irma', 'avos'], 95),
            $this->group('debutante_court', 'Corte da debutante', 'corte', ['madrinha', 'padrinho', 'dama_de_honra'], 85),
            $this->group('debutante_friends', 'Amigos da debutante', 'amigos', ['melhor_amiga', 'amigos'], 80),
        ], [
            $this->coverage('debutante_solo', 'Debutante em destaque', 'person', ['debutante'], null, 100),
            $this->coverage('debutante_family', 'Debutante com familia', 'group', ['debutante', 'mae_da_debutante', 'pai_da_debutante'], 'debutante_family', 95),
            $this->coverage('debutante_friends', 'Debutante com amigos', 'group', ['debutante', 'melhor_amiga', 'amigos'], 'debutante_friends', 85),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function birthday(): array
    {
        return $this->package('birthday', [
            $this->person('aniversariante', 'Aniversariante', 'guest', 'host_side', 'principal', 100),
            $this->person('mae', 'Mae', 'mother', 'host_side', 'familia', 90),
            $this->person('pai', 'Pai', 'father', 'host_side', 'familia', 90),
            $this->person('irmao_irma', 'Irmao(irma)', 'sibling', 'host_side', 'familia', 75),
            $this->person('avos', 'Avos', 'guest', 'host_side', 'familia', 70),
            $this->person('melhor_amigo', 'Melhor amigo(a)', 'friend', 'neutral', 'amigos', 70),
            $this->person('familia_proxima', 'Familia proxima', 'guest', 'host_side', 'familia', 65),
            $this->person('fotografo', 'Fotografo', 'vendor', 'neutral', 'fornecedor', 55),
            $this->person('decorador', 'Decorador', 'vendor', 'neutral', 'fornecedor', 45),
            $this->person('buffet', 'Buffet', 'vendor', 'neutral', 'fornecedor', 45),
        ], $this->socialRelations(), [
            $this->group('birthday_family', 'Familia do aniversariante', 'familia', ['aniversariante', 'mae', 'pai', 'irmao_irma', 'avos'], 90),
            $this->group('birthday_friends', 'Amigos importantes', 'amigos', ['aniversariante', 'melhor_amigo'], 75),
        ], [
            $this->coverage('birthday_person', 'Aniversariante em destaque', 'person', ['aniversariante'], null, 100),
            $this->coverage('birthday_family', 'Aniversariante com familia', 'group', ['aniversariante', 'mae', 'pai'], 'birthday_family', 90),
            $this->coverage('birthday_friends', 'Aniversariante com amigos', 'group', ['aniversariante', 'melhor_amigo'], 'birthday_friends', 75),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function corporate(): array
    {
        return $this->package('corporate', [
            $this->person('proprietario', 'Proprietario', 'executive', 'company_side', 'principal', 100),
            $this->person('socio', 'Socio', 'executive', 'company_side', 'principal', 95),
            $this->person('diretor', 'Diretor(a)', 'executive', 'company_side', 'corporativo', 90),
            $this->person('host', 'Host', 'executive', 'host_side', 'principal', 90),
            $this->person('palestrante', 'Palestrante', 'speaker', 'neutral', 'corporativo', 90),
            $this->person('executivo', 'Executivo', 'executive', 'company_side', 'corporativo', 80),
            $this->person('equipe', 'Equipe', 'staff', 'company_side', 'equipe', 60),
            $this->person('patrocinador', 'Patrocinador', 'executive', 'company_side', 'corporativo', 65),
            $this->person('imprensa', 'Imprensa', 'guest', 'neutral', 'corporativo', 55),
            $this->person('fotografo', 'Fotografo', 'vendor', 'neutral', 'fornecedor', 50),
            $this->person('fornecedor', 'Fornecedor', 'vendor', 'neutral', 'fornecedor', 45),
        ], $this->corporateRelations(), [
            $this->group('leadership', 'Lideranca', 'corporativo', ['proprietario', 'socio', 'diretor', 'host'], 95),
            $this->group('speakers', 'Palestrantes', 'corporativo', ['palestrante', 'executivo'], 85),
            $this->group('team', 'Equipe operacional', 'equipe', ['equipe', 'fornecedor'], 70),
        ], [
            $this->coverage('leadership', 'Lideranca em destaque', 'group', ['proprietario', 'socio', 'diretor'], 'leadership', 100),
            $this->coverage('speakers', 'Palestrantes e palco', 'group', ['palestrante'], 'speakers', 85),
            $this->coverage('sponsors', 'Patrocinadores', 'person', ['patrocinador'], null, 75),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function fair(): array
    {
        return $this->package('fair', [
            $this->person('responsavel_stand', 'Responsavel pelo stand', 'executive', 'company_side', 'principal', 100),
            $this->person('diretor', 'Diretor(a)', 'executive', 'company_side', 'corporativo', 90),
            $this->person('socio', 'Socio', 'executive', 'company_side', 'principal', 90),
            $this->person('expositor', 'Expositor', 'staff', 'company_side', 'equipe', 75),
            $this->person('equipe_comercial', 'Equipe comercial', 'staff', 'company_side', 'equipe', 70),
            $this->person('imprensa', 'Imprensa', 'guest', 'neutral', 'corporativo', 60),
            $this->person('patrocinador', 'Patrocinador', 'executive', 'company_side', 'corporativo', 65),
            $this->person('visitante_vip', 'Visitante VIP', 'guest', 'neutral', 'corporativo', 60),
        ], $this->corporateRelations(), [
            $this->group('stand_team', 'Equipe do stand', 'equipe', ['responsavel_stand', 'expositor', 'equipe_comercial'], 90),
            $this->group('fair_vips', 'VIPs e patrocinadores', 'corporativo', ['diretor', 'socio', 'patrocinador', 'visitante_vip'], 80),
        ], [
            $this->coverage('stand_team', 'Equipe do stand', 'group', ['responsavel_stand', 'expositor', 'equipe_comercial'], 'stand_team', 95),
            $this->coverage('vip_visits', 'Visitas VIP', 'group', ['visitante_vip', 'patrocinador'], 'fair_vips', 80),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function graduation(): array
    {
        return $this->package('graduation', [
            $this->person('formando', 'Formando(a)', 'guest', 'host_side', 'principal', 100),
            $this->person('mae', 'Mae', 'mother', 'host_side', 'familia', 90),
            $this->person('pai', 'Pai', 'father', 'host_side', 'familia', 90),
            $this->person('irmao_irma', 'Irmao(irma)', 'sibling', 'host_side', 'familia', 75),
            $this->person('familia_proxima', 'Familia proxima', 'guest', 'host_side', 'familia', 70),
            $this->person('patrono', 'Patrono', 'speaker', 'neutral', 'academico', 75),
            $this->person('paraninfo', 'Paraninfo', 'speaker', 'neutral', 'academico', 75),
            $this->person('professor', 'Professor', 'speaker', 'neutral', 'academico', 65),
            $this->person('amigos_turma', 'Amigos da turma', 'friend', 'neutral', 'amigos', 70),
            $this->person('fotografo', 'Fotografo', 'vendor', 'neutral', 'fornecedor', 50),
            $this->person('cerimonial', 'Cerimonial', 'vendor', 'neutral', 'fornecedor', 50),
        ], $this->socialRelations(), [
            $this->group('graduate_family', 'Familia do formando', 'familia', ['formando', 'mae', 'pai', 'irmao_irma', 'familia_proxima'], 90),
            $this->group('academic', 'Academico', 'academico', ['patrono', 'paraninfo', 'professor'], 75),
            $this->group('class_friends', 'Turma e amigos', 'amigos', ['formando', 'amigos_turma'], 80),
        ], [
            $this->coverage('graduate_solo', 'Formando em destaque', 'person', ['formando'], null, 100),
            $this->coverage('graduate_family', 'Formando com familia', 'group', ['formando', 'mae', 'pai'], 'graduate_family', 90),
            $this->coverage('class_friends', 'Formando com turma', 'group', ['formando', 'amigos_turma'], 'class_friends', 80),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function generic(): array
    {
        return $this->package('generic', [
            $this->person('principal', 'Pessoa principal', 'guest', 'neutral', 'principal', 80),
            $this->person('friend', 'Amigo(a)', 'friend', 'neutral', 'amigos', 50),
            $this->person('vendor', 'Fornecedor', 'vendor', 'neutral', 'fornecedor', 50),
        ], [
            $this->relation('friend_of', 'Amigo de', 'undirected'),
            $this->relation('works_with', 'Trabalha com', 'undirected'),
            $this->relation('vendor_of_event', 'Fornecedor do evento', 'directed'),
        ], [
            $this->group('important_people', 'Pessoas importantes', 'principal', ['principal'], 70),
        ], [
            $this->coverage('important_people', 'Pessoas importantes', 'person', ['principal'], null, 70),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $people
     * @param  array<int, array<string, mixed>>  $relations
     * @param  array<int, array<string, mixed>>  $groups
     * @param  array<int, array<string, mixed>>  $coverageTargets
     * @return array<string, mixed>
     */
    private function package(string $modelKey, array $people, array $relations, array $groups, array $coverageTargets): array
    {
        return [
            'model_key' => $modelKey,
            'people' => $people,
            'relations' => $relations,
            'groups' => $groups,
            'coverage_targets' => $coverageTargets,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function person(
        string $roleKey,
        string $roleLabel,
        string $type,
        string $side,
        string $roleFamily,
        int $importanceRank,
        ?string $description = null,
    ): array {
        return [
            'key' => $roleKey,
            'label' => $roleLabel,
            'role_key' => $roleKey,
            'role_label' => $roleLabel,
            'role_family' => $roleFamily,
            'type' => $type,
            'side' => $side,
            'importance_rank' => $importanceRank,
            'description' => $description,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function relation(string $type, string $label, string $directionality): array
    {
        return [
            'type' => $type,
            'label' => $label,
            'directionality' => $directionality,
        ];
    }

    /**
     * @param  array<int, string>  $memberRoleKeys
     * @return array<string, mixed>
     */
    private function group(string $key, string $label, string $roleFamily, array $memberRoleKeys, int $importanceRank): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'role_family' => $roleFamily,
            'member_role_keys' => $memberRoleKeys,
            'importance_rank' => $importanceRank,
        ];
    }

    /**
     * @param  array<int, string>  $roleKeys
     * @return array<string, mixed>
     */
    private function coverage(string $key, string $label, string $targetType, array $roleKeys, ?string $groupKey, int $priority): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'target_type' => $targetType,
            'role_keys' => $roleKeys,
            'group_key' => $groupKey,
            'priority' => $priority,
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function socialRelations(): array
    {
        return [
            $this->relation('mother_of', 'Mae de', 'directed'),
            $this->relation('father_of', 'Pai de', 'directed'),
            $this->relation('sibling_of', 'Irmao de', 'undirected'),
            $this->relation('friend_of', 'Amigo de', 'undirected'),
            $this->relation('vendor_of_event', 'Fornecedor do evento', 'directed'),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function corporateRelations(): array
    {
        return [
            $this->relation('manager_of', 'Gestor de', 'directed'),
            $this->relation('teammate_of', 'Colega de equipe', 'undirected'),
            $this->relation('works_with', 'Trabalha com', 'undirected'),
            $this->relation('speaker_with', 'Participa com', 'undirected'),
            $this->relation('sponsor_of', 'Patrocinador de', 'directed'),
        ];
    }
}
