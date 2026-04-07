<?php

namespace App\Modules\MediaIntelligence\Services;

class OpenRouterModelPolicy
{
    public function __construct(
        private readonly OpenRouterModelCatalog $catalog,
    ) {}

    /**
     * @return string|null
     */
    public function validationError(?string $modelKey, bool $requireJsonOutput): ?string
    {
        $resolvedModel = trim((string) $modelKey);

        if ($resolvedModel === '') {
            return 'Informe um modelo fixo do OpenRouter.';
        }

        $blockedModels = (array) config('media_intelligence.providers.openrouter.blocked_models', []);

        if (in_array($resolvedModel, $blockedModels, true)) {
            return 'OpenRouter exige modelo fixo no painel. Nao use openrouter/free nem openrouter/auto como configuracao salva.';
        }

        if (! str_contains($resolvedModel, '/')) {
            return 'Use um model id fixo no formato provider/model no OpenRouter.';
        }

        if (! $this->catalog->supportsImage($resolvedModel)) {
            return 'Modelo OpenRouter nao homologado para entrada com imagem no catalogo local.';
        }

        if ($requireJsonOutput && ! $this->catalog->supportsStructuredOutputs($resolvedModel)) {
            return 'Structured outputs exigem modelo homologado e compativel com json_schema no OpenRouter.';
        }

        return null;
    }
}
