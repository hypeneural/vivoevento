<?php

use App\Modules\Channels\Enums\ChannelType;
use App\Modules\Channels\Models\EventChannel;
use App\Modules\Billing\Models\Subscription;
use App\Modules\ContentModeration\Models\EventContentModerationSetting;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;
use App\Modules\Plans\Models\Plan;
use App\Modules\Wall\Models\EventWallSetting;
use App\Modules\WhatsApp\Models\WhatsAppInstance;

function makeJourneyControllerPatchPayload(WhatsAppInstance $instance, array $overrides = []): array
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
            'fallback_mode' => 'review',
            'analysis_scope' => 'image_and_text_context',
            'normalized_text_context_mode' => 'body_plus_caption',
        ],
        'media_intelligence' => [
            'enabled' => true,
            'provider_key' => 'vllm',
            'model_key' => 'Qwen/Qwen2.5-VL-3B-Instruct',
            'mode' => 'gate',
            'fallback_mode' => 'review',
            'context_scope' => 'image_and_text_context',
            'reply_scope' => 'image_and_text_context',
            'normalized_text_context_mode' => 'body_plus_caption',
            'reply_text_mode' => 'ai',
            'reply_text_enabled' => true,
            'reply_prompt_override' => 'Obrigado por participar desse momento.',
            'require_json_output' => true,
        ],
    ], $overrides);
}

function seedJourneyControllerEntitlements(int $organizationId, array $features): void
{
    $plan = Plan::query()->create([
        'code' => fake()->unique()->slug(2),
        'name' => 'Plano jornada controller',
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

it('returns the journey builder projection for an authorized event viewer', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'moderation_mode' => 'ai',
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
    ]);

    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'wall', 'is_enabled' => true]);

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

    EventContentModerationSetting::factory()->create([
        'event_id' => $event->id,
        'enabled' => true,
        'mode' => 'enforced',
        'fallback_mode' => 'review',
    ]);

    EventMediaIntelligenceSetting::factory()->gate()->create([
        'event_id' => $event->id,
        'enabled' => true,
        'reply_text_mode' => 'ai',
        'reply_text_enabled' => true,
    ]);

    EventWallSetting::factory()->live()->create([
        'event_id' => $event->id,
    ]);

    $response = $this->apiGet("/events/{$event->id}/journey-builder");

    $this->assertApiSuccess($response);

    $response->assertJsonStructure([
        'success',
        'data' => [
            'version',
            'event' => ['id', 'uuid', 'title', 'status', 'moderation_mode', 'modules'],
            'intake_defaults',
            'intake_channels',
            'settings' => ['moderation_mode', 'modules', 'content_moderation', 'media_intelligence', 'destinations'],
            'capabilities',
            'stages',
            'warnings',
            'simulation_presets',
            'summary' => ['human_text'],
        ],
        'meta' => ['request_id'],
    ]);

    $response->assertJsonPath('data.version', 'journey-builder-v1')
        ->assertJsonPath('data.event.id', $event->id)
        ->assertJsonPath('data.settings.moderation_mode', 'ai')
        ->assertJsonPath('data.capabilities.supports_print.available', false)
        ->assertJsonPath('data.summary.human_text', 'Quando a midia chega por WhatsApp privado, o Evento Vivo analisa risco e contexto com IA antes de publicar, responde automaticamente com IA e publica na galeria e no telao.')
        ->assertJsonPath('data.stages.0.id', 'entry')
        ->assertJsonPath('data.stages.1.id', 'processing')
        ->assertJsonPath('data.stages.2.id', 'decision')
        ->assertJsonPath('data.stages.3.id', 'output');
});

it('forbids reading the journey builder projection for an event from another organization', function () {
    [$user, $organization] = $this->actingAsOwner();

    $foreignOrganization = $this->createOrganization();
    $foreignEvent = Event::factory()->create([
        'organization_id' => $foreignOrganization->id,
    ]);

    $this->assertApiForbidden(
        $this->apiGet("/events/{$foreignEvent->id}/journey-builder"),
    );
});

it('keeps the journey builder endpoint query budget bounded for a fully configured event', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'moderation_mode' => 'ai',
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
    ]);

    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'wall', 'is_enabled' => true]);

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

    EventContentModerationSetting::factory()->create([
        'event_id' => $event->id,
        'enabled' => true,
    ]);

    EventMediaIntelligenceSetting::factory()->gate()->create([
        'event_id' => $event->id,
        'enabled' => true,
        'reply_text_mode' => 'ai',
        'reply_text_enabled' => true,
    ]);

    EventWallSetting::factory()->live()->create([
        'event_id' => $event->id,
    ]);

    $this->expectsDatabaseQueryCount(14);

    $this->assertApiSuccess(
        $this->apiGet("/events/{$event->id}/journey-builder"),
    );
});

it('updates the journey builder state through the aggregated patch endpoint', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
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
    ]);

    seedJourneyControllerEntitlements($organization->id, [
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

    EventWallSetting::factory()->live()->create([
        'event_id' => $event->id,
    ]);

    $instance = WhatsAppInstance::factory()->connected()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->apiPatch(
        "/events/{$event->id}/journey-builder",
        makeJourneyControllerPatchPayload($instance),
    );

    $this->assertApiSuccess($response);

    $response->assertJsonStructure([
        'success',
        'data' => [
            'version',
            'event' => ['id', 'uuid', 'title', 'status', 'moderation_mode', 'modules'],
            'intake_defaults',
            'intake_channels',
            'settings' => ['moderation_mode', 'modules', 'content_moderation', 'media_intelligence', 'destinations'],
            'capabilities',
            'stages',
            'warnings',
            'simulation_presets',
            'summary' => ['human_text'],
        ],
        'meta' => ['request_id'],
    ]);

    $response->assertJsonPath('data.settings.moderation_mode', 'ai')
        ->assertJsonPath('data.settings.content_moderation.mode', 'enforced')
        ->assertJsonPath('data.settings.media_intelligence.mode', 'gate')
        ->assertJsonPath('data.settings.media_intelligence.reply_text_mode', 'ai')
        ->assertJsonPath('data.settings.destinations.wall', true);

    $this->assertDatabaseHas('events', [
        'id' => $event->id,
        'moderation_mode' => 'ai',
        'default_whatsapp_instance_id' => $instance->id,
        'whatsapp_instance_mode' => 'shared',
    ]);

    $this->assertDatabaseHas('event_modules', [
        'event_id' => $event->id,
        'module_key' => 'wall',
        'is_enabled' => true,
    ]);

    $this->assertDatabaseHas('event_content_moderation_settings', [
        'event_id' => $event->id,
        'mode' => 'enforced',
        'fallback_mode' => 'review',
    ]);

    $this->assertDatabaseHas('event_media_intelligence_settings', [
        'event_id' => $event->id,
        'mode' => 'gate',
        'fallback_mode' => 'review',
        'reply_text_mode' => 'ai',
        'reply_text_enabled' => true,
    ]);
});

it('returns validation errors for an invalid journey builder patch payload', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    seedJourneyControllerEntitlements($organization->id, [
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
        'organization_id' => $organization->id,
    ]);

    $response = $this->apiPatch(
        "/events/{$event->id}/journey-builder",
        makeJourneyControllerPatchPayload($instance, [
            'media_intelligence' => [
                'fallback_mode' => 'skip',
            ],
        ]),
    );

    $this->assertApiValidationError($response, ['media_intelligence.fallback_mode']);
});

it('forbids updating the journey builder for a viewer without events.update permission', function () {
    [$user, $organization] = $this->actingAsViewer();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    seedJourneyControllerEntitlements($organization->id, [
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
        'organization_id' => $organization->id,
    ]);

    $this->assertApiForbidden(
        $this->apiPatch("/events/{$event->id}/journey-builder", makeJourneyControllerPatchPayload($instance)),
    );
});
