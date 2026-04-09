<?php

namespace App\Modules\MediaIntelligence\Services;

class ContextualModerationPresetCatalog
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return [
            'casamento_equilibrado' => [
                'key' => 'casamento_equilibrado',
                'label' => 'Casamento equilibrado',
                'allow_alcohol' => true,
                'allow_tobacco' => false,
                'required_people_context' => 'required',
                'blocked_terms_json' => [],
                'allowed_exceptions_json' => ['brinde com espumante'],
            ],
            'casamento_rigido' => [
                'key' => 'casamento_rigido',
                'label' => 'Casamento rigido',
                'allow_alcohol' => true,
                'allow_tobacco' => false,
                'required_people_context' => 'required',
                'blocked_terms_json' => ['fantasias', 'mascaras'],
                'allowed_exceptions_json' => ['brinde com espumante'],
            ],
            'formatura' => [
                'key' => 'formatura',
                'label' => 'Formatura',
                'allow_alcohol' => true,
                'allow_tobacco' => false,
                'required_people_context' => 'required',
                'blocked_terms_json' => [],
                'allowed_exceptions_json' => ['brinde com champagne'],
            ],
            'corporativo_restrito' => [
                'key' => 'corporativo_restrito',
                'label' => 'Corporativo restrito',
                'allow_alcohol' => false,
                'allow_tobacco' => false,
                'required_people_context' => 'required',
                'blocked_terms_json' => [],
                'allowed_exceptions_json' => [],
            ],
            'aniversario_infantil' => [
                'key' => 'aniversario_infantil',
                'label' => 'Aniversario infantil',
                'allow_alcohol' => false,
                'allow_tobacco' => false,
                'required_people_context' => 'required',
                'blocked_terms_json' => ['armas cenicas'],
                'allowed_exceptions_json' => [],
            ],
            'homologacao_livre' => [
                'key' => 'homologacao_livre',
                'label' => 'Homologacao livre',
                'allow_alcohol' => true,
                'allow_tobacco' => true,
                'required_people_context' => 'optional',
                'blocked_terms_json' => [],
                'allowed_exceptions_json' => [],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(?string $key): array
    {
        $catalog = $this->all();
        $resolvedKey = is_string($key) && isset($catalog[$key]) ? $key : 'homologacao_livre';

        return $catalog[$resolvedKey];
    }
}
