<?php

use App\Modules\MediaIntelligence\Services\ContextualModerationPromptBuilder;

it('builds contextual moderation instructions from the structured policy instead of depending on a raw prompt', function () {
    $built = app(ContextualModerationPromptBuilder::class)->build(
        eventName: 'Casamento Ana e Pedro',
        policySnapshot: [
            'contextual_policy_preset_key' => 'casamento_equilibrado',
            'contextual_policy_preset_label' => 'Casamento equilibrado',
            'policy_version' => 'contextual-policy-v1',
            'context_scope' => 'image_and_text_context',
            'allow_alcohol' => true,
            'allow_tobacco' => false,
            'required_people_context' => 'required',
            'blocked_terms_json' => ['mascaras', 'armas cenicas'],
            'allowed_exceptions_json' => ['brinde com espumante'],
            'freeform_instruction' => 'Se a cena estiver ambigua, prefira review.',
            'caption_style_prompt' => 'Legenda curta e positiva.',
        ],
        normalizedTextContext: 'Entrada dos noivos na pista.',
        replyInstruction: 'Responda de forma curta e calorosa quando a midia for elegivel.',
    );

    expect(data_get($built, 'prompt_template'))->toContain('politica estruturada')
        ->and(data_get($built, 'prompt_resolved'))->toContain('Casamento Ana e Pedro')
        ->and(data_get($built, 'prompt_resolved'))->toContain('alcool permitido: sim')
        ->and(data_get($built, 'prompt_resolved'))->toContain('tabaco permitido: nao')
        ->and(data_get($built, 'prompt_resolved'))->toContain('presenca de pessoas: obrigatoria')
        ->and(data_get($built, 'prompt_resolved'))->toContain('bloqueios adicionais: mascaras, armas cenicas')
        ->and(data_get($built, 'prompt_resolved'))->toContain('excecoes permitidas: brinde com espumante')
        ->and(data_get($built, 'prompt_resolved'))->toContain('texto associado ao envio: Entrada dos noivos na pista.')
        ->and(data_get($built, 'prompt_resolved'))->toContain('instrucao complementar do operador: Se a cena estiver ambigua, prefira review.')
        ->and(data_get($built, 'policy_json.contextual_policy_preset_key'))->toBe('casamento_equilibrado');
});
