<?php

use App\Modules\Billing\Models\Subscription;
use App\Modules\Channels\Enums\ChannelType;
use App\Modules\Channels\Models\EventChannel;
use App\Modules\Events\Models\Event;
use App\Modules\Plans\Models\Plan;
use App\Modules\WhatsApp\Models\WhatsAppGroupBinding;
use App\Modules\WhatsApp\Models\WhatsAppInstance;

it('creates an event with intake defaults and intake channels when entitlements allow it', function () {
    [$user, $organization] = $this->actingAsOwner();

    seedEventChannelEntitlements($organization, [
        'channels.whatsapp_groups.enabled' => 'true',
        'channels.whatsapp_groups.max' => '2',
        'channels.whatsapp_direct.enabled' => 'true',
        'channels.public_upload.enabled' => 'true',
        'channels.whatsapp.shared_instance.enabled' => 'true',
    ]);

    $instance = WhatsAppInstance::factory()->connected()->create([
        'organization_id' => $organization->id,
        'is_default' => true,
    ]);

    $response = $this->apiPost('/events', [
        'organization_id' => $organization->id,
        'title' => 'Evento com intake',
        'event_type' => 'wedding',
        'intake_defaults' => [
            'whatsapp_instance_id' => $instance->id,
            'whatsapp_instance_mode' => 'shared',
        ],
        'intake_channels' => [
            'whatsapp_groups' => [
                'enabled' => true,
                'groups' => [
                    [
                        'group_external_id' => '120363425796926861-group',
                        'group_name' => 'Evento Vivo 1',
                        'is_active' => true,
                        'auto_feedback_enabled' => true,
                    ],
                ],
            ],
            'whatsapp_direct' => [
                'enabled' => true,
                'media_inbox_code' => 'ANAEJOAO',
                'session_ttl_minutes' => 180,
            ],
            'public_upload' => [
                'enabled' => true,
            ],
        ],
    ]);

    $this->assertApiSuccess($response, 201);

    $eventId = $response->json('data.id');

    $response->assertJsonPath('data.intake_defaults.whatsapp_instance_id', $instance->id)
        ->assertJsonPath('data.intake_defaults.whatsapp_instance_mode', 'shared')
        ->assertJsonPath('data.intake_channels.whatsapp_groups.enabled', true)
        ->assertJsonPath('data.intake_channels.whatsapp_groups.groups.0.group_external_id', '120363425796926861-group')
        ->assertJsonPath('data.intake_channels.whatsapp_direct.enabled', true)
        ->assertJsonPath('data.intake_channels.whatsapp_direct.media_inbox_code', 'ANAEJOAO')
        ->assertJsonPath('data.intake_channels.public_upload.enabled', true);

    $this->assertDatabaseHas('events', [
        'id' => $eventId,
        'default_whatsapp_instance_id' => $instance->id,
        'whatsapp_instance_mode' => 'shared',
    ]);

    $this->assertDatabaseHas('event_channels', [
        'event_id' => $eventId,
        'channel_type' => ChannelType::WhatsAppGroup->value,
        'status' => 'active',
    ]);

    $this->assertDatabaseHas('event_channels', [
        'event_id' => $eventId,
        'channel_type' => 'whatsapp_direct',
        'status' => 'active',
    ]);

    $this->assertDatabaseHas('event_channels', [
        'event_id' => $eventId,
        'channel_type' => ChannelType::PublicUploadLink->value,
        'status' => 'active',
    ]);

    $this->assertDatabaseHas('whatsapp_group_bindings', [
        'organization_id' => $organization->id,
        'event_id' => $eventId,
        'instance_id' => $instance->id,
        'group_external_id' => '120363425796926861-group',
        'binding_type' => 'event_gallery',
        'is_active' => true,
    ]);
});

it('returns intake channels in the event detail payload', function () {
    [$user, $organization] = $this->actingAsOwner();

    $instance = WhatsAppInstance::factory()->connected()->create([
        'organization_id' => $organization->id,
    ]);

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'default_whatsapp_instance_id' => $instance->id,
        'whatsapp_instance_mode' => 'shared',
    ]);

    EventChannel::query()->create([
        'event_id' => $event->id,
        'channel_type' => ChannelType::WhatsAppGroup->value,
        'provider' => 'zapi',
        'label' => 'WhatsApp grupos',
        'status' => 'active',
        'config_json' => [
            'groups' => [
                [
                    'group_external_id' => '120363425796926861-group',
                    'group_name' => 'Evento Vivo 1',
                    'is_active' => true,
                    'auto_feedback_enabled' => true,
                ],
            ],
        ],
    ]);

    EventChannel::query()->create([
        'event_id' => $event->id,
        'channel_type' => 'whatsapp_direct',
        'provider' => 'zapi',
        'label' => 'WhatsApp direto',
        'status' => 'active',
        'config_json' => [
            'media_inbox_code' => 'ANAEJOAO',
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

    WhatsAppGroupBinding::query()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'instance_id' => $instance->id,
        'group_external_id' => '120363425796926861-group',
        'group_name' => 'Evento Vivo 1',
        'binding_type' => 'event_gallery',
        'is_active' => true,
        'metadata_json' => ['auto_feedback_enabled' => true],
    ]);

    $response = $this->apiGet("/events/{$event->id}");

    $this->assertApiSuccess($response);

    $response->assertJsonPath('data.intake_defaults.whatsapp_instance_id', $instance->id)
        ->assertJsonPath('data.intake_defaults.whatsapp_instance_mode', 'shared')
        ->assertJsonPath('data.intake_channels.whatsapp_groups.enabled', true)
        ->assertJsonPath('data.intake_channels.whatsapp_groups.groups.0.group_name', 'Evento Vivo 1')
        ->assertJsonPath('data.intake_channels.whatsapp_direct.enabled', true)
        ->assertJsonPath('data.intake_channels.whatsapp_direct.media_inbox_code', 'ANAEJOAO')
        ->assertJsonPath('data.intake_channels.public_upload.enabled', true);
});

it('updates intake channels and deactivates removed whatsapp group bindings', function () {
    [$user, $organization] = $this->actingAsOwner();

    seedEventChannelEntitlements($organization, [
        'channels.whatsapp_groups.enabled' => 'true',
        'channels.whatsapp_groups.max' => '3',
        'channels.whatsapp_direct.enabled' => 'true',
        'channels.public_upload.enabled' => 'true',
        'channels.whatsapp.shared_instance.enabled' => 'true',
    ]);

    $instance = WhatsAppInstance::factory()->connected()->create([
        'organization_id' => $organization->id,
    ]);

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    EventChannel::query()->create([
        'event_id' => $event->id,
        'channel_type' => ChannelType::WhatsAppGroup->value,
        'provider' => 'zapi',
        'label' => 'WhatsApp grupos',
        'status' => 'active',
        'config_json' => [
            'groups' => [
                [
                    'group_external_id' => 'grupo-antigo',
                    'group_name' => 'Grupo antigo',
                    'is_active' => true,
                    'auto_feedback_enabled' => true,
                ],
            ],
        ],
    ]);

    EventChannel::query()->create([
        'event_id' => $event->id,
        'channel_type' => 'whatsapp_direct',
        'provider' => 'zapi',
        'label' => 'WhatsApp direto',
        'status' => 'active',
        'config_json' => [
            'media_inbox_code' => 'CODIGOANTIGO',
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

    WhatsAppGroupBinding::query()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'instance_id' => $instance->id,
        'group_external_id' => 'grupo-antigo',
        'group_name' => 'Grupo antigo',
        'binding_type' => 'event_gallery',
        'is_active' => true,
        'metadata_json' => ['auto_feedback_enabled' => true],
    ]);

    $response = $this->apiPatch("/events/{$event->id}", [
        'intake_defaults' => [
            'whatsapp_instance_id' => $instance->id,
            'whatsapp_instance_mode' => 'shared',
        ],
        'intake_channels' => [
            'whatsapp_groups' => [
                'enabled' => true,
                'groups' => [
                    [
                        'group_external_id' => 'grupo-novo',
                        'group_name' => 'Grupo novo',
                        'is_active' => true,
                        'auto_feedback_enabled' => false,
                    ],
                ],
            ],
            'whatsapp_direct' => [
                'enabled' => false,
            ],
            'public_upload' => [
                'enabled' => false,
            ],
        ],
    ]);

    $this->assertApiSuccess($response);

    $response->assertJsonPath('data.intake_channels.whatsapp_groups.groups.0.group_external_id', 'grupo-novo')
        ->assertJsonPath('data.intake_channels.whatsapp_direct.enabled', false)
        ->assertJsonPath('data.intake_channels.public_upload.enabled', false);

    $this->assertDatabaseHas('whatsapp_group_bindings', [
        'event_id' => $event->id,
        'group_external_id' => 'grupo-antigo',
        'is_active' => false,
    ]);

    $this->assertDatabaseHas('whatsapp_group_bindings', [
        'event_id' => $event->id,
        'group_external_id' => 'grupo-novo',
        'is_active' => true,
    ]);

    expect(EventChannel::query()
        ->where('event_id', $event->id)
        ->where('channel_type', 'whatsapp_direct')
        ->exists())->toBeFalse();

    expect(EventChannel::query()
        ->where('event_id', $event->id)
        ->where('channel_type', ChannelType::PublicUploadLink->value)
        ->exists())->toBeFalse();
});

it('blocks whatsapp groups above the commercial entitlement limit', function () {
    [$user, $organization] = $this->actingAsOwner();

    seedEventChannelEntitlements($organization, [
        'channels.whatsapp_groups.enabled' => 'true',
        'channels.whatsapp_groups.max' => '1',
        'channels.whatsapp.shared_instance.enabled' => 'true',
    ]);

    $instance = WhatsAppInstance::factory()->connected()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->apiPost('/events', [
        'organization_id' => $organization->id,
        'title' => 'Evento limite grupos',
        'event_type' => 'birthday',
        'intake_defaults' => [
            'whatsapp_instance_id' => $instance->id,
            'whatsapp_instance_mode' => 'shared',
        ],
        'intake_channels' => [
            'whatsapp_groups' => [
                'enabled' => true,
                'groups' => [
                    ['group_external_id' => 'grupo-1', 'group_name' => 'Grupo 1', 'is_active' => true],
                    ['group_external_id' => 'grupo-2', 'group_name' => 'Grupo 2', 'is_active' => true],
                ],
            ],
        ],
    ]);

    $this->assertApiValidationError($response, ['intake_channels.whatsapp_groups.groups']);

    expect(Event::query()->where('title', 'Evento limite grupos')->exists())->toBeFalse();
});

it('blocks whatsapp direct and public upload when the event entitlements do not allow them', function () {
    [$user, $organization] = $this->actingAsOwner();

    seedEventChannelEntitlements($organization, [
        'channels.whatsapp_groups.enabled' => 'true',
        'channels.whatsapp_groups.max' => '1',
        'channels.whatsapp.shared_instance.enabled' => 'true',
        'channels.whatsapp_direct.enabled' => 'false',
        'channels.public_upload.enabled' => 'false',
    ]);

    $instance = WhatsAppInstance::factory()->connected()->create([
        'organization_id' => $organization->id,
    ]);

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->apiPatch("/events/{$event->id}", [
        'intake_defaults' => [
            'whatsapp_instance_id' => $instance->id,
            'whatsapp_instance_mode' => 'shared',
        ],
        'intake_channels' => [
            'whatsapp_direct' => ['enabled' => true],
            'public_upload' => ['enabled' => true],
        ],
    ]);

    $this->assertApiValidationError($response, [
        'intake_channels.whatsapp_direct.enabled',
        'intake_channels.public_upload.enabled',
    ]);
});

it('blocks dedicated whatsapp instance mode when entitlement is missing or when another event already owns the instance', function () {
    [$user, $organization] = $this->actingAsOwner();

    seedEventChannelEntitlements($organization, [
        'channels.whatsapp_groups.enabled' => 'true',
        'channels.whatsapp_groups.max' => '2',
        'channels.whatsapp.shared_instance.enabled' => 'true',
        'channels.whatsapp.dedicated_instance.enabled' => 'false',
    ]);

    $instance = WhatsAppInstance::factory()->connected()->create([
        'organization_id' => $organization->id,
    ]);

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->apiPatch("/events/{$event->id}", [
        'intake_defaults' => [
            'whatsapp_instance_id' => $instance->id,
            'whatsapp_instance_mode' => 'dedicated',
        ],
        'intake_channels' => [
            'whatsapp_groups' => [
                'enabled' => true,
                'groups' => [
                    ['group_external_id' => 'grupo-1', 'group_name' => 'Grupo 1', 'is_active' => true],
                ],
            ],
        ],
    ]);

    $this->assertApiValidationError($response, ['intake_defaults.whatsapp_instance_mode']);

    seedEventChannelEntitlements($organization, [
        'channels.whatsapp_groups.enabled' => 'true',
        'channels.whatsapp_groups.max' => '2',
        'channels.whatsapp.dedicated_instance.enabled' => 'true',
        'channels.whatsapp.dedicated_instance.max_per_event' => '1',
    ]);

    $ownerEvent = Event::factory()->create([
        'organization_id' => $organization->id,
        'default_whatsapp_instance_id' => $instance->id,
        'whatsapp_instance_mode' => 'dedicated',
    ]);

    $otherEvent = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $conflictResponse = $this->apiPatch("/events/{$otherEvent->id}", [
        'intake_defaults' => [
            'whatsapp_instance_id' => $instance->id,
            'whatsapp_instance_mode' => 'dedicated',
        ],
        'intake_channels' => [
            'whatsapp_groups' => [
                'enabled' => true,
                'groups' => [
                    ['group_external_id' => 'grupo-2', 'group_name' => 'Grupo 2', 'is_active' => true],
                ],
            ],
        ],
    ]);

    $this->assertApiValidationError($conflictResponse, ['intake_defaults.whatsapp_instance_id']);

    expect($ownerEvent->fresh()->default_whatsapp_instance_id)->toBe($instance->id);
});

function seedEventChannelEntitlements($organization, array $features): void
{
    $plan = Plan::create([
        'code' => fake()->unique()->slug(2),
        'name' => 'Plano canais',
        'audience' => 'b2b',
        'status' => 'active',
    ]);

    foreach ($features as $featureKey => $featureValue) {
        $plan->features()->create([
            'feature_key' => $featureKey,
            'feature_value' => $featureValue,
        ]);
    }

    Subscription::create([
        'organization_id' => $organization->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'starts_at' => now(),
        'renews_at' => now()->addMonth(),
    ]);
}
