<?php

use App\Modules\Events\Models\Event;
use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;
use App\Modules\MediaIntelligence\Models\MediaReplyPromptPreset;
use App\Modules\MediaIntelligence\Models\MediaIntelligenceGlobalSetting;
use App\Modules\MediaIntelligence\Services\OpenAiCompatibleVisualReasoningPayloadFactory;
use App\Modules\MediaProcessing\Models\EventMedia;

it('injects the resolved event-aware automatic response instruction from global settings', function () {
    MediaIntelligenceGlobalSetting::query()->updateOrCreate(
        ['id' => 1],
        ['reply_text_prompt' => 'Use {nome_do_evento} de forma natural e gere uma frase curta com emoji coerente com a imagem.'],
    );

    $event = Event::factory()->create([
        'title' => 'Formatura 2026',
    ]);

    $media = EventMedia::factory()->make([
        'event_id' => $event->id,
        'caption' => 'Entrada no palco',
    ]);
    $media->setRelation('event', $event);

    $settings = EventMediaIntelligenceSetting::factory()->make([
        'event_id' => $event->id,
        'reply_text_mode' => 'ai',
        'reply_prompt_override' => null,
    ]);

    $payload = app(OpenAiCompatibleVisualReasoningPayloadFactory::class)->build(
        $media,
        $settings,
        'https://example.com/test.jpg',
        [
            'model' => 'openai/gpt-4.1-mini',
            'temperature' => 0.1,
            'max_completion_tokens' => 300,
        ],
    );

    expect(data_get($payload, 'messages.1.content.0.text'))->toContain('Use Formatura 2026 de forma natural e gere uma frase curta com emoji coerente com a imagem.')
        ->and(data_get($payload, 'messages.1.content.0.text'))->toContain('Formatura 2026')
        ->and(data_get($payload, 'messages.1.content.0.text'))->not->toContain('reply_text');
});

it('injects the event instruction override instead of the global instruction', function () {
    MediaIntelligenceGlobalSetting::query()->updateOrCreate(
        ['id' => 1],
        ['reply_text_prompt' => 'Prompt global nao deve prevalecer.'],
    );

    $event = Event::factory()->create([
        'title' => 'Aniversario',
    ]);

    $media = EventMedia::factory()->make([
        'event_id' => $event->id,
        'caption' => null,
    ]);
    $media->setRelation('event', $event);

    $settings = EventMediaIntelligenceSetting::factory()->make([
        'event_id' => $event->id,
        'reply_text_mode' => 'ai',
        'reply_prompt_override' => 'Use {nome_do_evento} com um tom divertido e emoji de festa.',
    ]);

    $payload = app(OpenAiCompatibleVisualReasoningPayloadFactory::class)->build(
        $media,
        $settings,
        'https://example.com/test.jpg',
        [
            'model' => 'openai/gpt-4.1-mini',
            'temperature' => 0.1,
            'max_completion_tokens' => 300,
        ],
    );

    expect(data_get($payload, 'messages.1.content.0.text'))->toContain('Use Aniversario com um tom divertido e emoji de festa.')
        ->and(data_get($payload, 'messages.1.content.0.text'))->not->toContain('Prompt global nao deve prevalecer.')
        ->and(data_get($payload, 'messages.1.content.0.text'))->not->toContain('reply_text');
});

it('injects the selected preset before the effective event-aware instruction', function () {
    $preset = MediaReplyPromptPreset::factory()->create([
        'name' => 'Formaturas',
        'prompt_template' => 'Use um tom celebrativo e elegante, coerente com a cena.',
    ]);

    MediaIntelligenceGlobalSetting::query()->updateOrCreate(
        ['id' => 1],
        ['reply_text_prompt' => 'Use {nome_do_evento} apenas quando soar natural.'],
    );

    $event = Event::factory()->create([
        'title' => 'Formatura 2026',
    ]);

    $media = EventMedia::factory()->make([
        'event_id' => $event->id,
    ]);
    $media->setRelation('event', $event);

    $settings = EventMediaIntelligenceSetting::factory()->make([
        'event_id' => $event->id,
        'reply_text_mode' => 'ai',
        'reply_prompt_preset_id' => $preset->id,
        'reply_prompt_override' => null,
    ]);

    $payload = app(OpenAiCompatibleVisualReasoningPayloadFactory::class)->build(
        $media,
        $settings,
        'https://example.com/test.jpg',
        [
            'model' => 'openai/gpt-4.1-mini',
            'temperature' => 0.1,
            'max_completion_tokens' => 300,
        ],
    );

    expect(data_get($payload, 'messages.1.content.0.text'))
        ->toContain('Use um tom celebrativo e elegante, coerente com a cena.')
        ->and(data_get($payload, 'messages.1.content.0.text'))
        ->toContain('Use Formatura 2026 apenas quando soar natural.');
});
