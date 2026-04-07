<?php

use App\Modules\MediaIntelligence\Services\OpenRouterModelPolicy;

it('accepts a homologated fixed openrouter model that supports image and structured outputs', function () {
    config()->set('media_intelligence.providers.openrouter.allowed_models', [
        'openai/gpt-4.1-mini' => [
            'supports_image' => true,
            'supports_json_schema' => true,
        ],
    ]);

    expect(app(OpenRouterModelPolicy::class)->validationError('openai/gpt-4.1-mini', true))->toBeNull();
});

it('rejects blocked router aliases in openrouter settings', function () {
    config()->set('media_intelligence.providers.openrouter.blocked_models', ['openrouter/auto', 'openrouter/free']);

    expect(app(OpenRouterModelPolicy::class)->validationError('openrouter/auto', true))
        ->toBe('OpenRouter exige modelo fixo no painel. Nao use openrouter/free nem openrouter/auto como configuracao salva.');
});

it('rejects models that are not homologated for image input', function () {
    config()->set('media_intelligence.providers.openrouter.allowed_models', [
        'openai/gpt-4.1-mini' => [
            'supports_image' => true,
            'supports_json_schema' => true,
        ],
    ]);

    expect(app(OpenRouterModelPolicy::class)->validationError('openai/gpt-4.1-nano', true))
        ->toBe('Modelo OpenRouter nao homologado para entrada com imagem no catalogo local.');
});

it('rejects homologated models without structured output support when json output is required', function () {
    config()->set('media_intelligence.providers.openrouter.allowed_models', [
        'vendor/vision-no-schema' => [
            'supports_image' => true,
            'supports_json_schema' => false,
        ],
    ]);

    expect(app(OpenRouterModelPolicy::class)->validationError('vendor/vision-no-schema', true))
        ->toBe('Structured outputs exigem modelo homologado e compativel com json_schema no OpenRouter.');
});
