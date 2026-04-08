<?php

use App\Modules\Events\Models\Event;
use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;
use App\Modules\MediaIntelligence\Models\MediaIntelligenceGlobalSetting;
use App\Modules\MediaIntelligence\Services\ContextualModerationPolicyResolver;

it('resolves contextual policy from preset, global settings and event overrides with per-field sources', function () {
    $event = Event::factory()->create();

    MediaIntelligenceGlobalSetting::query()->create([
        'id' => 1,
        'enabled' => true,
        'provider_key' => 'openrouter',
        'model_key' => 'openai/gpt-4.1-mini',
        'mode' => 'gate',
        'contextual_policy_preset_key' => 'casamento_equilibrado',
        'allow_tobacco' => false,
        'blocked_terms_json' => ['mascaras'],
        'allowed_exceptions_json' => ['brinde com espumante'],
        'freeform_instruction' => 'Evite imagens sem pessoas quando o contexto estiver ambiguo.',
    ]);

    EventMediaIntelligenceSetting::factory()->create([
        'event_id' => $event->id,
        'enabled' => true,
        'contextual_policy_preset_key' => 'corporativo_restrito',
        'allow_alcohol' => false,
        'allowed_exceptions_json' => ['palestrante no palco'],
        'freeform_instruction' => 'Aceite palco e plateia, mas nao objetos isolados.',
    ]);

    $resolved = app(ContextualModerationPolicyResolver::class)->resolveForEvent($event);

    expect(data_get($resolved, 'snapshot.contextual_policy_preset_key'))->toBe('corporativo_restrito')
        ->and(data_get($resolved, 'snapshot.contextual_policy_preset_label'))->toBe('Corporativo restrito')
        ->and(data_get($resolved, 'snapshot.provider_key'))->toBe('openrouter')
        ->and(data_get($resolved, 'snapshot.model_key'))->toBe('openai/gpt-4.1-mini')
        ->and(data_get($resolved, 'snapshot.mode'))->toBe('gate')
        ->and(data_get($resolved, 'snapshot.allow_alcohol'))->toBeFalse()
        ->and(data_get($resolved, 'snapshot.allow_tobacco'))->toBeFalse()
        ->and(data_get($resolved, 'snapshot.required_people_context'))->toBe('required')
        ->and(data_get($resolved, 'snapshot.blocked_terms_json'))->toBe(['mascaras'])
        ->and(data_get($resolved, 'snapshot.allowed_exceptions_json'))->toBe(['palestrante no palco'])
        ->and(data_get($resolved, 'snapshot.freeform_instruction'))->toBe('Aceite palco e plateia, mas nao objetos isolados.')
        ->and(data_get($resolved, 'sources.contextual_policy_preset_key'))->toBe('event_setting')
        ->and(data_get($resolved, 'sources.allow_alcohol'))->toBe('event_setting')
        ->and(data_get($resolved, 'sources.allow_tobacco'))->toBe('global_setting')
        ->and(data_get($resolved, 'sources.required_people_context'))->toBe('preset')
        ->and(data_get($resolved, 'sources.blocked_terms_json'))->toBe('global_setting')
        ->and(data_get($resolved, 'sources.allowed_exceptions_json'))->toBe('event_setting')
        ->and(data_get($resolved, 'sources.freeform_instruction'))->toBe('event_setting');
});
