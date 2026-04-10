<?php

use App\Modules\Channels\Enums\ChannelType;
use App\Modules\Channels\Models\EventChannel;
use App\Modules\ContentModeration\Models\EventContentModerationSetting;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;
use App\Modules\Wall\Models\EventWallSetting;

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
