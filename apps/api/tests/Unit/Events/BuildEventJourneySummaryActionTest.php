<?php

use App\Modules\ContentModeration\Models\EventContentModerationSetting;
use App\Modules\Events\Actions\BuildEventJourneySummaryAction;
use App\Modules\Events\Models\Event;
use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;

function makeSummaryEvent(array $attributes = []): Event
{
    return Event::factory()->create(array_merge([
        'moderation_mode' => 'manual',
    ], $attributes));
}

function makeContentModeration(array $attributes = []): EventContentModerationSetting
{
    return EventContentModerationSetting::factory()->make(array_merge([
        'enabled' => false,
        'mode' => 'enforced',
        'fallback_mode' => 'review',
    ], $attributes));
}

function makeMediaIntelligence(array $attributes = []): EventMediaIntelligenceSetting
{
    return EventMediaIntelligenceSetting::factory()->make(array_merge([
        'enabled' => false,
        'mode' => 'enrich_only',
        'reply_text_mode' => 'disabled',
        'reply_text_enabled' => false,
        'fallback_mode' => 'review',
    ], $attributes));
}

it('builds a readable manual review summary for the operator', function () {
    $summary = app(BuildEventJourneySummaryAction::class)->execute(
        event: makeSummaryEvent(['moderation_mode' => 'manual']),
        intakeChannels: [
            'whatsapp_direct' => ['enabled' => true],
            'whatsapp_groups' => ['enabled' => false],
            'telegram' => ['enabled' => false],
            'public_upload' => ['enabled' => true],
        ],
        contentModeration: makeContentModeration(),
        mediaIntelligence: makeMediaIntelligence(),
        destinations: ['gallery' => true, 'wall' => false, 'print' => false],
    );

    expect($summary['human_text'])->toBe(
        'Quando a midia chega por WhatsApp privado e link de envio, o Evento Vivo envia para revisao manual, nao envia resposta automatica e publica na galeria.'
    );
});

it('builds an ai summary that reflects safety gate, contextual gate and ai replies', function () {
    $summary = app(BuildEventJourneySummaryAction::class)->execute(
        event: makeSummaryEvent(['moderation_mode' => 'ai']),
        intakeChannels: [
            'whatsapp_direct' => ['enabled' => true],
            'whatsapp_groups' => ['enabled' => false],
            'telegram' => ['enabled' => false],
            'public_upload' => ['enabled' => false],
        ],
        contentModeration: makeContentModeration([
            'enabled' => true,
            'mode' => 'enforced',
        ]),
        mediaIntelligence: makeMediaIntelligence([
            'enabled' => true,
            'mode' => 'gate',
            'reply_text_mode' => 'ai',
            'reply_text_enabled' => true,
        ]),
        destinations: ['gallery' => true, 'wall' => true, 'print' => false],
    );

    expect($summary['human_text'])->toBe(
        'Quando a midia chega por WhatsApp privado, o Evento Vivo analisa risco e contexto com IA antes de publicar, responde automaticamente com IA e publica na galeria e no telao.'
    );
});

it('builds an ai summary for observe-only safety with enrich-only and fixed replies', function () {
    $summary = app(BuildEventJourneySummaryAction::class)->execute(
        event: makeSummaryEvent(['moderation_mode' => 'ai']),
        intakeChannels: [
            'whatsapp_direct' => ['enabled' => false],
            'whatsapp_groups' => ['enabled' => false],
            'telegram' => ['enabled' => true],
            'public_upload' => ['enabled' => false],
        ],
        contentModeration: makeContentModeration([
            'enabled' => true,
            'mode' => 'observe_only',
        ]),
        mediaIntelligence: makeMediaIntelligence([
            'enabled' => true,
            'mode' => 'enrich_only',
            'reply_text_mode' => 'fixed_random',
            'reply_text_enabled' => true,
        ]),
        destinations: ['gallery' => true, 'wall' => false, 'print' => false],
    );

    expect($summary['human_text'])->toBe(
        'Quando a midia chega por Telegram, o Evento Vivo usa IA para entender melhor a midia e sinalizar revisao quando necessario, responde com mensagem pronta e publica na galeria.'
    );
});

it('falls back cleanly when there are no active intake channels', function () {
    $summary = app(BuildEventJourneySummaryAction::class)->execute(
        event: makeSummaryEvent(['moderation_mode' => 'none']),
        intakeChannels: [
            'whatsapp_direct' => ['enabled' => false],
            'whatsapp_groups' => ['enabled' => false],
            'telegram' => ['enabled' => false],
            'public_upload' => ['enabled' => false],
        ],
        contentModeration: makeContentModeration(),
        mediaIntelligence: makeMediaIntelligence(),
        destinations: ['gallery' => true, 'wall' => false, 'print' => false],
    );

    expect($summary['human_text'])->toBe(
        'Sem canais de recebimento ativos, a jornada fica pronta para configuracao e nenhuma midia nova entra no fluxo.'
    );
});

it('never promises print output in the summary text', function () {
    $summary = app(BuildEventJourneySummaryAction::class)->execute(
        event: makeSummaryEvent(['moderation_mode' => 'manual']),
        intakeChannels: [
            'whatsapp_direct' => ['enabled' => true],
            'whatsapp_groups' => ['enabled' => false],
            'telegram' => ['enabled' => false],
            'public_upload' => ['enabled' => false],
        ],
        contentModeration: makeContentModeration(),
        mediaIntelligence: makeMediaIntelligence(),
        destinations: ['gallery' => true, 'wall' => false, 'print' => true],
    );

    expect($summary['human_text'])
        ->not->toContain('impress')
        ->and($summary['human_text'])->toContain('publica na galeria');
});

