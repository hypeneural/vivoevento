<?php

use App\Modules\Channels\Enums\ChannelType;
use App\Modules\Channels\Models\EventChannel;
use App\Modules\Billing\Models\Subscription;
use App\Modules\ContentModeration\Models\EventContentModerationSetting;
use App\Modules\Events\Actions\UpdateEventJourneyAction;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;
use App\Modules\MediaIntelligence\Models\MediaReplyPromptPreset;
use App\Modules\Plans\Models\Plan;
use App\Modules\Wall\Models\EventWallSetting;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use Illuminate\Validation\ValidationException;

function makeJourneyUpdateEvent(array $attributes = []): Event
{
    return Event::factory()->create(array_merge([
        'moderation_mode' => 'manual',
        'current_entitlements_json' => [
            'channels' => [
                'whatsapp_direct' => ['enabled' => true],
                'whatsapp_groups' => ['enabled' => true, 'max' => 3],
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
            ],
        ],
    ], $attributes));
}

function seedJourneyActionEntitlements(int $organizationId, array $features): void
{
    $plan = Plan::query()->create([
        'code' => fake()->unique()->slug(2),
        'name' => 'Plano jornada action',
        'audience' => 'b2b',
        'status' => 'active',
    ]);

    foreach ($features as $featureKey => $featureValue) {
        $plan->features()->create([
            'feature_key' => $featureKey,
            'feature_value' => $featureValue,
        ]);
    }

    Subscription::query()->create([
        'organization_id' => $organizationId,
        'plan_id' => $plan->id,
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'starts_at' => now(),
        'renews_at' => now()->addMonth(),
    ]);
}

function makeJourneyUpdatePayload(WhatsAppInstance $instance, array $overrides = []): array
{
    return array_replace_recursive([
        'moderation_mode' => 'ai',
        'modules' => [
            'live' => true,
            'wall' => true,
        ],
        'intake_defaults' => [
            'whatsapp_instance_id' => $instance->id,
            'whatsapp_instance_mode' => 'shared',
        ],
        'intake_channels' => [
            'whatsapp_direct' => [
                'enabled' => true,
                'media_inbox_code' => 'NOIVA2026',
                'session_ttl_minutes' => 180,
            ],
            'telegram' => [
                'enabled' => true,
                'bot_username' => 'EventoVivoBot',
                'media_inbox_code' => 'NOIVABOT',
                'session_ttl_minutes' => 180,
            ],
            'public_upload' => [
                'enabled' => true,
            ],
        ],
        'content_moderation' => [
            'enabled' => true,
            'provider_key' => 'openai',
            'mode' => 'enforced',
            'threshold_version' => 'wedding-v1',
            'fallback_mode' => 'review',
            'analysis_scope' => 'image_and_text_context',
            'normalized_text_context_mode' => 'body_plus_caption',
        ],
        'media_intelligence' => [
            'enabled' => true,
            'provider_key' => 'vllm',
            'model_key' => 'Qwen/Qwen2.5-VL-3B-Instruct',
            'mode' => 'gate',
            'prompt_version' => 'contextual-v2',
            'fallback_mode' => 'review',
            'context_scope' => 'image_and_text_context',
            'reply_scope' => 'image_and_text_context',
            'normalized_text_context_mode' => 'body_plus_caption',
            'reply_text_mode' => 'ai',
            'reply_text_enabled' => true,
            'reply_prompt_override' => 'Obrigado por enviar esse momento.',
            'require_json_output' => true,
        ],
    ], $overrides);
}

it('updates channels, moderation, safety and media intelligence in one aggregated save and returns the refreshed projection', function () {
    $event = makeJourneyUpdateEvent();
    seedJourneyActionEntitlements($event->organization_id, [
        'channels.whatsapp_direct.enabled' => 'true',
        'channels.whatsapp_groups.enabled' => 'true',
        'channels.whatsapp_groups.max' => '3',
        'channels.public_upload.enabled' => 'true',
        'channels.telegram.enabled' => 'true',
        'channels.whatsapp.shared_instance.enabled' => 'true',
        'channels.whatsapp.dedicated_instance.enabled' => 'true',
        'channels.whatsapp.dedicated_instance.max_per_event' => '1',
        'modules.wall' => 'true',
    ]);
    $instance = WhatsAppInstance::factory()->connected()->create([
        'organization_id' => $event->organization_id,
    ]);

    EventWallSetting::factory()->live()->create([
        'event_id' => $event->id,
    ]);

    $payload = app(UpdateEventJourneyAction::class)
        ->execute($event, makeJourneyUpdatePayload($instance))
        ->toArray();

    $event->refresh();

    expect($event->moderation_mode?->value)->toBe('ai')
        ->and($event->default_whatsapp_instance_id)->toBe($instance->id)
        ->and($event->whatsapp_instance_mode)->toBe('shared')
        ->and(EventModule::query()->where('event_id', $event->id)->where('module_key', 'live')->value('is_enabled'))->toBeTrue()
        ->and(EventModule::query()->where('event_id', $event->id)->where('module_key', 'wall')->value('is_enabled'))->toBeTrue()
        ->and(EventChannel::query()->where('event_id', $event->id)->where('channel_type', ChannelType::WhatsAppDirect->value)->exists())->toBeTrue()
        ->and(EventChannel::query()->where('event_id', $event->id)->where('channel_type', ChannelType::TelegramBot->value)->exists())->toBeTrue()
        ->and(EventChannel::query()->where('event_id', $event->id)->where('channel_type', ChannelType::PublicUploadLink->value)->exists())->toBeTrue()
        ->and(EventContentModerationSetting::query()->where('event_id', $event->id)->value('mode'))->toBe('enforced')
        ->and(EventMediaIntelligenceSetting::query()->where('event_id', $event->id)->value('mode'))->toBe('gate')
        ->and($payload['settings']['moderation_mode'])->toBe('ai')
        ->and($payload['settings']['content_moderation']['mode'])->toBe('enforced')
        ->and($payload['settings']['media_intelligence']['mode'])->toBe('gate')
        ->and($payload['settings']['media_intelligence']['reply_text_mode'])->toBe('ai')
        ->and($payload['settings']['destinations']['wall'])->toBeTrue()
        ->and($payload['summary']['human_text'])->toBe(
            'Quando a midia chega por WhatsApp privado, Telegram e link de envio, o Evento Vivo analisa risco e contexto com IA antes de publicar, responde automaticamente com IA e publica na galeria e no telao.'
        );
});

it('rolls back aggregated changes when an intake entitlement fails during the save', function () {
    $event = makeJourneyUpdateEvent([
        'current_entitlements_json' => [
            'channels' => [
                'whatsapp_direct' => ['enabled' => true],
                'whatsapp_groups' => ['enabled' => true, 'max' => 3],
                'public_upload' => ['enabled' => true],
                'telegram' => ['enabled' => false],
                'blacklist' => ['enabled' => true],
                'whatsapp' => [
                    'shared_instance' => ['enabled' => true],
                    'dedicated_instance' => ['enabled' => true, 'max_per_event' => 1],
                ],
            ],
            'modules' => [
                'wall' => true,
            ],
        ],
    ]);
    seedJourneyActionEntitlements($event->organization_id, [
        'channels.whatsapp_direct.enabled' => 'true',
        'channels.whatsapp_groups.enabled' => 'true',
        'channels.whatsapp_groups.max' => '3',
        'channels.public_upload.enabled' => 'true',
        'channels.telegram.enabled' => 'false',
        'channels.whatsapp.shared_instance.enabled' => 'true',
        'channels.whatsapp.dedicated_instance.enabled' => 'true',
        'channels.whatsapp.dedicated_instance.max_per_event' => '1',
        'modules.wall' => 'true',
    ]);

    $instance = WhatsAppInstance::factory()->connected()->create([
        'organization_id' => $event->organization_id,
    ]);

    expect(fn () => app(UpdateEventJourneyAction::class)->execute($event, makeJourneyUpdatePayload($instance)))
        ->toThrow(ValidationException::class);

    $event->refresh();

    expect($event->moderation_mode?->value)->toBe('manual')
        ->and(EventModule::query()->where('event_id', $event->id)->count())->toBe(0)
        ->and(EventChannel::query()->where('event_id', $event->id)->count())->toBe(0)
        ->and(EventContentModerationSetting::query()->where('event_id', $event->id)->count())->toBe(0)
        ->and(EventMediaIntelligenceSetting::query()->where('event_id', $event->id)->count())->toBe(0);
});

it('rolls back earlier writes when media intelligence gate fallback is invalid', function () {
    $event = makeJourneyUpdateEvent();
    seedJourneyActionEntitlements($event->organization_id, [
        'channels.whatsapp_direct.enabled' => 'true',
        'channels.whatsapp_groups.enabled' => 'true',
        'channels.whatsapp_groups.max' => '3',
        'channels.public_upload.enabled' => 'true',
        'channels.telegram.enabled' => 'true',
        'channels.whatsapp.shared_instance.enabled' => 'true',
        'channels.whatsapp.dedicated_instance.enabled' => 'true',
        'channels.whatsapp.dedicated_instance.max_per_event' => '1',
        'modules.wall' => 'true',
    ]);
    $instance = WhatsAppInstance::factory()->connected()->create([
        'organization_id' => $event->organization_id,
    ]);

    $payload = makeJourneyUpdatePayload($instance, [
        'media_intelligence' => [
            'fallback_mode' => 'skip',
        ],
    ]);

    expect(fn () => app(UpdateEventJourneyAction::class)->execute($event, $payload))
        ->toThrow(ValidationException::class, 'Eventos com VLM em gate devem usar fallback review para nunca aprovar por erro tecnico.');

    $event->refresh();

    expect($event->moderation_mode?->value)->toBe('manual')
        ->and(EventModule::query()->where('event_id', $event->id)->count())->toBe(0)
        ->and(EventChannel::query()->where('event_id', $event->id)->count())->toBe(0)
        ->and(EventContentModerationSetting::query()->where('event_id', $event->id)->count())->toBe(0)
        ->and(EventMediaIntelligenceSetting::query()->where('event_id', $event->id)->count())->toBe(0);
});

it('persists fixed random reply templates in the aggregated journey save', function () {
    $event = makeJourneyUpdateEvent();
    seedJourneyActionEntitlements($event->organization_id, [
        'channels.whatsapp_direct.enabled' => 'true',
        'channels.whatsapp_groups.enabled' => 'true',
        'channels.whatsapp_groups.max' => '3',
        'channels.public_upload.enabled' => 'true',
        'channels.telegram.enabled' => 'true',
        'channels.whatsapp.shared_instance.enabled' => 'true',
        'channels.whatsapp.dedicated_instance.enabled' => 'true',
        'channels.whatsapp.dedicated_instance.max_per_event' => '1',
        'modules.wall' => 'true',
    ]);
    $instance = WhatsAppInstance::factory()->connected()->create([
        'organization_id' => $event->organization_id,
    ]);

    $payload = app(UpdateEventJourneyAction::class)
        ->execute($event, makeJourneyUpdatePayload($instance, [
            'media_intelligence' => [
                'mode' => 'enrich_only',
                'reply_text_mode' => 'fixed_random',
                'reply_text_enabled' => true,
                'reply_fixed_templates' => [
                    'Que registro lindo desse momento.',
                    'Recebemos sua midia com carinho.',
                ],
                'reply_prompt_override' => null,
            ],
        ]))
        ->toArray();

    $settings = EventMediaIntelligenceSetting::query()->where('event_id', $event->id)->firstOrFail();

    expect($settings->resolvedReplyTextMode())->toBe('fixed_random')
        ->and($settings->reply_fixed_templates_json)->toBe([
            'Que registro lindo desse momento.',
            'Recebemos sua midia com carinho.',
        ])
        ->and($payload['settings']['media_intelligence']['reply_text_mode'])->toBe('fixed_random')
        ->and($payload['summary']['human_text'])->toContain('responde com mensagem pronta');
});

it('persists ai reply preset and override in the aggregated journey save', function () {
    $event = makeJourneyUpdateEvent();
    seedJourneyActionEntitlements($event->organization_id, [
        'channels.whatsapp_direct.enabled' => 'true',
        'channels.whatsapp_groups.enabled' => 'true',
        'channels.whatsapp_groups.max' => '3',
        'channels.public_upload.enabled' => 'true',
        'channels.telegram.enabled' => 'true',
        'channels.whatsapp.shared_instance.enabled' => 'true',
        'channels.whatsapp.dedicated_instance.enabled' => 'true',
        'channels.whatsapp.dedicated_instance.max_per_event' => '1',
        'modules.wall' => 'true',
    ]);
    $instance = WhatsAppInstance::factory()->connected()->create([
        'organization_id' => $event->organization_id,
    ]);
    $preset = MediaReplyPromptPreset::factory()->create();

    $payload = app(UpdateEventJourneyAction::class)
        ->execute($event, makeJourneyUpdatePayload($instance, [
            'media_intelligence' => [
                'reply_text_mode' => 'ai',
                'reply_text_enabled' => true,
                'reply_prompt_preset_id' => $preset->id,
                'reply_prompt_override' => 'Responda como cerimonial simpatico e objetivo.',
            ],
        ]))
        ->toArray();

    $settings = EventMediaIntelligenceSetting::query()->where('event_id', $event->id)->firstOrFail();

    expect($settings->reply_prompt_preset_id)->toBe($preset->id)
        ->and($settings->reply_prompt_override)->toBe('Responda como cerimonial simpatico e objetivo.')
        ->and($payload['settings']['media_intelligence']['reply_text_mode'])->toBe('ai')
        ->and($payload['summary']['human_text'])->toContain('responde automaticamente com IA');
});
