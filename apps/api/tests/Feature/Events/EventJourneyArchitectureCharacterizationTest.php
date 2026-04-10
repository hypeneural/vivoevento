<?php

use App\Modules\Channels\Enums\ChannelType;
use App\Modules\Channels\Models\EventChannel;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\Hub\Models\EventHubSetting;
use App\Modules\WhatsApp\Models\WhatsAppInstance;

it('returns a single event detail aggregate with intake state and hub builder config together', function () {
    [$user, $organization] = $this->actingAsOwner();

    $instance = WhatsAppInstance::factory()->connected()->create([
        'organization_id' => $organization->id,
    ]);

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'default_whatsapp_instance_id' => $instance->id,
        'whatsapp_instance_mode' => 'shared',
    ]);

    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'live', 'is_enabled' => true]);
    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'hub', 'is_enabled' => true]);

    EventChannel::query()->create([
        'event_id' => $event->id,
        'channel_type' => 'whatsapp_direct',
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
        'channel_type' => ChannelType::PublicUploadLink->value,
        'provider' => 'eventovivo',
        'external_id' => $event->upload_slug,
        'label' => 'Link de upload',
        'status' => 'active',
        'config_json' => [],
    ]);

    EventHubSetting::query()->create([
        'event_id' => $event->id,
        'is_enabled' => true,
        'headline' => 'Hub do evento',
        'builder_config_json' => [
            'version' => 1,
            'layout_key' => 'hero-cards',
            'theme_key' => 'sunset',
            'theme_tokens' => [
                'page_background' => '#111827',
                'page_accent' => '#f97316',
                'surface_background' => '#1f2937',
                'surface_border' => '#374151',
                'text_primary' => '#fff7ed',
                'text_secondary' => '#fdba74',
                'hero_overlay_color' => '#030712',
            ],
            'block_order' => ['hero', 'cta_list'],
            'blocks' => [
                'hero' => [
                    'enabled' => true,
                    'height' => 'md',
                ],
                'cta_list' => [
                    'enabled' => true,
                    'style' => 'outline',
                    'size' => 'md',
                ],
            ],
        ],
    ]);

    $response = $this->apiGet("/events/{$event->id}");

    $this->assertApiSuccess($response);

    $response->assertJsonPath('data.intake_defaults.whatsapp_instance_id', $instance->id)
        ->assertJsonPath('data.intake_defaults.whatsapp_instance_mode', 'shared')
        ->assertJsonPath('data.intake_channels.whatsapp_direct.enabled', true)
        ->assertJsonPath('data.intake_channels.whatsapp_direct.media_inbox_code', 'NOIVAEJOAO')
        ->assertJsonPath('data.intake_channels.public_upload.enabled', true)
        ->assertJsonPath('data.hub.is_enabled', true)
        ->assertJsonPath('data.hub.builder_config.layout_key', 'hero-cards')
        ->assertJsonPath('data.hub.builder_config.blocks.cta_list.style', 'outline');
});
