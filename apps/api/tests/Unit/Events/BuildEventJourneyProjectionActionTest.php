<?php

use App\Modules\Channels\Enums\ChannelType;
use App\Modules\Channels\Models\EventChannel;
use App\Modules\ContentModeration\Models\EventContentModerationSetting;
use App\Modules\Events\Actions\BuildEventJourneyProjectionAction;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;
use App\Modules\Wall\Models\EventWallSetting;

function buildJourneyProjectionPayload(Event $event): array
{
    return app(BuildEventJourneyProjectionAction::class)->execute($event)->toArray();
}

function journeyNode(array $payload, string $nodeId): array
{
    foreach ($payload['stages'] as $stage) {
        foreach ($stage['nodes'] as $node) {
            if ($node['id'] === $nodeId) {
                return $node;
            }
        }
    }

    throw new RuntimeException("Journey node [{$nodeId}] was not found.");
}

function createJourneyEvent(array $attributes = []): Event
{
    return Event::factory()->create(array_merge([
        'moderation_mode' => 'manual',
        'current_entitlements_json' => [
            'channels' => [
                'whatsapp_groups' => ['enabled' => true, 'max' => 5],
                'whatsapp_direct' => ['enabled' => true],
                'public_upload' => ['enabled' => true],
                'telegram' => ['enabled' => true],
                'blacklist' => ['enabled' => true],
                'whatsapp' => [
                    'shared_instance' => ['enabled' => true],
                    'dedicated_instance' => ['enabled' => true, 'max_per_event' => 1],
                ],
            ],
            'modules' => [
                'wall' => true,
                'play' => true,
            ],
        ],
    ], $attributes));
}

it('builds the four read-only operational stages with stable nodes and branches', function () {
    $event = createJourneyEvent();

    EventChannel::query()->create([
        'event_id' => $event->id,
        'channel_type' => ChannelType::WhatsAppDirect->value,
        'provider' => 'zapi',
        'label' => 'WhatsApp direto',
        'status' => 'active',
        'config_json' => [
            'media_inbox_code' => 'NOIVAEJOAO',
            'session_ttl_minutes' => 180,
        ],
    ]);

    $payload = buildJourneyProjectionPayload($event);

    expect(array_column($payload['stages'], 'id'))->toBe(['entry', 'processing', 'decision', 'output'])
        ->and(array_column($payload['stages'], 'label'))->toBe(['Entrada', 'Processamento', 'Decisao', 'Saida'])
        ->and(journeyNode($payload, 'entry_whatsapp_direct')['branches'][0]['id'])->toBe('default')
        ->and(array_column(journeyNode($payload, 'decision_event_moderation_mode')['branches'], 'id'))
        ->toBe(['approved', 'review', 'blocked', 'default'])
        ->and($payload['event']['moderation_mode'])->toBe('manual')
        ->and($payload['summary']['human_text'])->toBe(
            'Quando a midia chega por WhatsApp privado, o Evento Vivo envia para revisao manual, nao envia resposta automatica e publica na galeria.'
        )
        ->and($payload['version'])->toBe('journey-builder-v1');
});

it('reflects active and locked intake channels from event state and entitlements', function () {
    $event = createJourneyEvent([
        'current_entitlements_json' => [
            'channels' => [
                'whatsapp_groups' => ['enabled' => false, 'max' => 0],
                'whatsapp_direct' => ['enabled' => true],
                'public_upload' => ['enabled' => true],
                'telegram' => ['enabled' => false],
                'blacklist' => ['enabled' => false],
                'whatsapp' => [
                    'shared_instance' => ['enabled' => true],
                    'dedicated_instance' => ['enabled' => false],
                ],
            ],
        ],
    ]);

    EventChannel::query()->create([
        'event_id' => $event->id,
        'channel_type' => ChannelType::WhatsAppDirect->value,
        'provider' => 'zapi',
        'label' => 'WhatsApp direto',
        'status' => 'active',
        'config_json' => [
            'media_inbox_code' => 'NOIVAEJOAO',
            'session_ttl_minutes' => 180,
        ],
    ]);

    EventChannel::query()->create([
        'event_id' => $event->id,
        'channel_type' => ChannelType::WhatsAppGroup->value,
        'provider' => 'zapi',
        'label' => 'WhatsApp grupos',
        'status' => 'active',
        'config_json' => [
            'groups' => [
                ['group_external_id' => 'grupo-1', 'group_name' => 'Familia', 'is_active' => true],
            ],
        ],
    ]);

    EventChannel::query()->create([
        'event_id' => $event->id,
        'channel_type' => ChannelType::TelegramBot->value,
        'provider' => 'telegram',
        'label' => 'Telegram',
        'status' => 'active',
        'config_json' => [
            'bot_username' => 'EventoVivoBot',
            'media_inbox_code' => 'NOIVAEJOAO',
        ],
    ]);

    EventChannel::query()->create([
        'event_id' => $event->id,
        'channel_type' => ChannelType::PublicUploadLink->value,
        'provider' => 'eventovivo',
        'label' => 'Link de upload',
        'status' => 'active',
        'config_json' => [],
    ]);

    $payload = buildJourneyProjectionPayload($event);

    expect(journeyNode($payload, 'entry_whatsapp_direct')['status'])->toBe('active')
        ->and(journeyNode($payload, 'entry_whatsapp_direct')['config_preview']['available'])->toBeTrue()
        ->and(journeyNode($payload, 'entry_whatsapp_groups')['status'])->toBe('locked')
        ->and(journeyNode($payload, 'entry_whatsapp_groups')['config_preview']['available'])->toBeFalse()
        ->and(journeyNode($payload, 'entry_telegram')['status'])->toBe('locked')
        ->and(journeyNode($payload, 'entry_public_upload')['status'])->toBe('active')
        ->and($payload['warnings'])->toContain('O canal WhatsApp grupos esta ativo, mas o evento nao tem entitlement para usa-lo.')
        ->and($payload['warnings'])->toContain('O canal Telegram esta ativo, mas o evento nao tem entitlement para usa-lo.');
});

it('projects event moderation modes without creating a flow DSL', function (string $mode, string $summary, string $activeBranch) {
    $event = createJourneyEvent(['moderation_mode' => $mode]);

    $payload = buildJourneyProjectionPayload($event);
    $node = journeyNode($payload, 'decision_event_moderation_mode');

    expect($node['config_preview']['moderation_mode'])->toBe($mode)
        ->and($node['summary'])->toBe($summary)
        ->and(collect($node['branches'])->firstWhere('id', $activeBranch)['active'])->toBeTrue()
        ->and($payload['settings']['moderation_mode'])->toBe($mode);
})->with([
    ['none', 'Aprova automaticamente sem fila manual.', 'approved'],
    ['manual', 'Envia midias para revisao humana antes de publicar.', 'review'],
    ['ai', 'Usa politicas de IA para aprovar, revisar ou bloquear.', 'default'],
]);

it('reflects Safety settings for disabled, enforced and observe-only modes', function (bool $enabled, string $mode, string $expectedStatus, string $expectedSummary) {
    $event = createJourneyEvent(['moderation_mode' => 'ai']);

    EventContentModerationSetting::factory()->create([
        'event_id' => $event->id,
        'enabled' => $enabled,
        'mode' => $mode,
        'fallback_mode' => 'review',
    ]);

    $payload = buildJourneyProjectionPayload($event);
    $node = journeyNode($payload, 'processing_safety_ai');

    expect($node['status'])->toBe($expectedStatus)
        ->and($node['summary'])->toBe($expectedSummary)
        ->and($node['config_preview']['enabled'])->toBe($enabled)
        ->and($node['config_preview']['mode'])->toBe($mode)
        ->and($node['config_preview']['fallback_mode'])->toBe('review')
        ->and($payload['settings']['content_moderation']['enabled'])->toBe($enabled);
})->with([
    [false, 'enforced', 'inactive', 'Safety por IA desligado.'],
    [true, 'enforced', 'active', 'Bloqueia ou envia para revisao conforme risco detectado.'],
    [true, 'observe_only', 'active', 'Analisa risco e registra sinais sem bloquear automaticamente.'],
]);

it('reflects Media Intelligence gate/enrich modes and automatic reply modes', function (string $mode, string $replyMode, bool $replyEnabled, string $expectedDecisionStatus) {
    $event = createJourneyEvent(['moderation_mode' => 'ai']);

    EventMediaIntelligenceSetting::factory()->create([
        'event_id' => $event->id,
        'enabled' => true,
        'mode' => $mode,
        'fallback_mode' => 'review',
        'reply_text_mode' => $replyMode,
        'reply_text_enabled' => $replyEnabled,
    ]);

    $payload = buildJourneyProjectionPayload($event);
    $processingNode = journeyNode($payload, 'processing_media_intelligence');
    $decisionNode = journeyNode($payload, 'decision_context_gate');
    $replyNode = journeyNode($payload, 'output_reply_text');

    expect($processingNode['status'])->toBe('active')
        ->and($processingNode['config_preview']['mode'])->toBe($mode)
        ->and($decisionNode['status'])->toBe($expectedDecisionStatus)
        ->and($replyNode['active'])->toBe($replyMode !== 'disabled')
        ->and($replyNode['config_preview']['reply_text_mode'])->toBe($replyMode)
        ->and($payload['settings']['media_intelligence']['reply_text_mode'])->toBe($replyMode);
})->with([
    ['enrich_only', 'disabled', false, 'inactive'],
    ['enrich_only', 'fixed_random', true, 'inactive'],
    ['gate', 'ai', true, 'active'],
]);

it('marks gallery and wall outputs from real settings while keeping print unavailable in V1', function () {
    $event = createJourneyEvent(['status' => 'active']);

    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'live', 'is_enabled' => true]);
    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'wall', 'is_enabled' => true]);
    EventWallSetting::factory()->live()->create(['event_id' => $event->id]);

    $payload = buildJourneyProjectionPayload($event);

    expect(journeyNode($payload, 'output_gallery')['status'])->toBe('required')
        ->and(journeyNode($payload, 'output_wall')['status'])->toBe('active')
        ->and(journeyNode($payload, 'output_print')['status'])->toBe('unavailable')
        ->and($payload['capabilities']['supports_print']['available'])->toBeFalse()
        ->and($payload['capabilities']['supports_wall_output']['enabled'])->toBeTrue();
});

it('keeps the read-only projection query budget bounded', function () {
    $event = createJourneyEvent(['moderation_mode' => 'ai']);

    EventContentModerationSetting::factory()->create(['event_id' => $event->id, 'enabled' => true]);
    EventMediaIntelligenceSetting::factory()->gate()->create(['event_id' => $event->id, 'enabled' => true]);
    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'wall', 'is_enabled' => true]);
    EventWallSetting::factory()->live()->create(['event_id' => $event->id]);

    $this->expectsDatabaseQueryCount(8);

    $payload = buildJourneyProjectionPayload($event);

    expect($payload['event']['id'])->toBe($event->id);
});
