<?php

use App\Modules\Events\Models\Event;
use App\Modules\InboundMedia\Models\InboundMessage;
use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;
use App\Modules\MediaIntelligence\Models\EventMediaVlmEvaluation;
use App\Modules\MediaIntelligence\Models\MediaIntelligenceGlobalSetting;
use App\Modules\MediaIntelligence\Services\PublishedMediaReplyTextResolver;
use App\Modules\MediaProcessing\Models\EventMedia;

it('returns null when automatic reply mode is disabled', function () {
    $event = Event::factory()->create([
        'title' => 'Evento Teste',
    ]);

    EventMediaIntelligenceSetting::factory()->create([
        'event_id' => $event->id,
        'reply_text_mode' => 'disabled',
        'reply_text_enabled' => false,
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
    ]);

    expect(app(PublishedMediaReplyTextResolver::class)->resolve($media))->toBeNull();
});

it('returns the latest ai reply when the mode is ai', function () {
    $event = Event::factory()->create([
        'title' => 'Evento Teste',
    ]);

    EventMediaIntelligenceSetting::factory()->create([
        'event_id' => $event->id,
        'reply_text_mode' => 'ai',
        'reply_text_enabled' => true,
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
    ]);

    EventMediaVlmEvaluation::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'reply_text' => 'Memorias que fazem o coracao sorrir! ðŸŽ‰ðŸ“¸',
    ]);

    $resolver = app(PublishedMediaReplyTextResolver::class);

    expect($resolver->resolve($media))
        ->toBe('Memorias que fazem o coracao sorrir! ðŸŽ‰ðŸ“¸');

    expect($resolver->resolveContext($media))
        ->toMatchArray([
            'mode' => 'ai',
            'source' => 'vlm',
            'reply_text' => 'Memorias que fazem o coracao sorrir! ðŸŽ‰ðŸ“¸',
        ]);
});

it('returns a deterministic fixed template from event settings when the mode is fixed_random', function () {
    $event = Event::factory()->create([
        'title' => 'Casamento da Ana',
    ]);

    $inboundMessage = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'whatsapp',
        'message_id' => 'msg-001',
        'provider_message_id' => 'wamid-fixed-seed',
        'message_type' => 'image',
        'sender_phone' => '5511999999999',
        'status' => 'received',
        'received_at' => now(),
    ]);

    EventMediaIntelligenceSetting::factory()->create([
        'event_id' => $event->id,
        'reply_text_mode' => 'fixed_random',
        'reply_text_enabled' => true,
        'reply_fixed_templates_json' => [
            'Memorias de {nome_do_evento}! ðŸŽ‰ðŸ“¸',
            'Momento especial de {nome_do_evento}! âœ¨',
        ],
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $inboundMessage->id,
    ]);

    $resolver = app(PublishedMediaReplyTextResolver::class);
    $resolved = $resolver->resolve($media);

    expect($resolved)->not->toBeNull()
        ->and($resolved)->toContain('Casamento da Ana');

    expect($resolver->resolveContext($media))
        ->toMatchArray([
            'mode' => 'fixed_random',
            'source' => 'event_fixed_template',
        ]);
});

it('falls back to global fixed templates when the event has no fixed templates configured', function () {
    $event = Event::factory()->create([
        'title' => 'Formatura 2026',
    ]);

    MediaIntelligenceGlobalSetting::query()->updateOrCreate(
        ['id' => 1],
        [
            'reply_text_prompt' => MediaIntelligenceGlobalSetting::defaultReplyTextPrompt(),
            'reply_text_fixed_templates_json' => [
                'Celebrando {nome_do_evento}! ðŸŽ“âœ¨',
            ],
        ],
    );

    EventMediaIntelligenceSetting::factory()->create([
        'event_id' => $event->id,
        'reply_text_mode' => 'fixed_random',
        'reply_text_enabled' => true,
        'reply_fixed_templates_json' => [],
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
    ]);

    $resolver = app(PublishedMediaReplyTextResolver::class);

    expect($resolver->resolve($media))
        ->toBe('Celebrando Formatura 2026! ðŸŽ“âœ¨');

    expect($resolver->resolveContext($media))
        ->toMatchArray([
            'mode' => 'fixed_random',
            'source' => 'global_fixed_template',
            'reply_text' => 'Celebrando Formatura 2026! ðŸŽ“âœ¨',
        ]);
});
