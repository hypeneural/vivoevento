<?php

use App\Modules\Events\Models\Event;
use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;
use App\Modules\MediaIntelligence\Models\MediaReplyPromptPreset;
use App\Modules\MediaIntelligence\Models\MediaIntelligenceGlobalSetting;
use App\Modules\MediaIntelligence\Services\MediaReplyTextPromptResolver;

it('resolves the event name variable from the global instruction', function () {
    MediaIntelligenceGlobalSetting::query()->updateOrCreate(
        ['id' => 1],
        ['reply_text_prompt' => 'Use {nome_do_evento} apenas quando soar natural.'],
    );

    $event = Event::factory()->create([
        'title' => 'Casamento da Ana e do Pedro',
    ]);

    $settings = EventMediaIntelligenceSetting::factory()->make([
        'event_id' => $event->id,
        'reply_text_mode' => 'ai',
        'reply_prompt_override' => null,
    ]);

    $context = app(MediaReplyTextPromptResolver::class)->promptContext($settings, $event->title);

    expect($context)->not->toBeNull()
        ->and($context['template'])->toBe('Use {nome_do_evento} apenas quando soar natural.')
        ->and($context['variables'])->toBe([
            'nome_do_evento' => 'Casamento da Ana e do Pedro',
        ])
        ->and($context['resolved'])->toBe('Use Casamento da Ana e do Pedro apenas quando soar natural.');
});

it('collapses leftover spaces when the event name variable has no value', function () {
    MediaIntelligenceGlobalSetting::query()->updateOrCreate(
        ['id' => 1],
        ['reply_text_prompt' => 'Use {nome_do_evento} apenas quando soar natural.'],
    );

    $settings = EventMediaIntelligenceSetting::factory()->make([
        'reply_text_mode' => 'ai',
        'reply_prompt_override' => null,
    ]);

    $context = app(MediaReplyTextPromptResolver::class)->promptContext($settings, null);

    expect($context)->not->toBeNull()
        ->and($context['variables'])->toBe([
            'nome_do_evento' => '',
        ])
        ->and($context['resolved'])->toBe('Use apenas quando soar natural.');
});

it('combines the selected preset with the manual or inherited instruction template', function () {
    $preset = MediaReplyPromptPreset::factory()->create([
        'name' => 'Casamentos',
        'prompt_template' => 'Use um tom romantico, elegante e coerente com a cena.',
    ]);

    $context = app(MediaReplyTextPromptResolver::class)->composePromptContext(
        eventName: 'Casamento da Ana e do Pedro',
        instructionTemplate: 'Use {nome_do_evento} apenas quando fizer sentido.',
        instructionSource: 'manual',
        preset: $preset,
        presetSource: 'teste',
    );

    expect($context)->not->toBeNull()
        ->and($context['preset_id'])->toBe($preset->id)
        ->and($context['preset_name'])->toBe('Casamentos')
        ->and($context['preset_source'])->toBe('teste')
        ->and($context['template'])->toContain('Use um tom romantico, elegante e coerente com a cena.')
        ->and($context['template'])->toContain('Use {nome_do_evento} apenas quando fizer sentido.')
        ->and($context['resolved'])->toContain('Casamento da Ana e do Pedro');
});
