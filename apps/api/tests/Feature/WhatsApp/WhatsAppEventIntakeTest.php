<?php

use App\Modules\Channels\Enums\ChannelType;
use App\Modules\Channels\Models\EventChannel;
use App\Modules\Events\Enums\EventStatus;
use App\Modules\Events\Models\Event;
use App\Modules\InboundMedia\Models\InboundMessage;
use App\Modules\InboundMedia\Jobs\ProcessInboundWebhookJob as ProcessInboundMediaWebhookJob;
use App\Modules\WhatsApp\Enums\GroupBindingType;
use App\Modules\WhatsApp\Enums\MessageDirection;
use App\Modules\WhatsApp\Jobs\SendWhatsAppMessageJob;
use App\Modules\WhatsApp\Models\WhatsAppGroupBinding;
use App\Modules\WhatsApp\Models\WhatsAppInboxSession;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

function loadZApiIntakeFixture(string $name, array $overrides = []): array
{
    $payload = json_decode(
        file_get_contents(base_path("tests/Fixtures/WhatsApp/ZApi/{$name}.json")),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    return array_replace_recursive($payload, $overrides);
}

function makeZapiGroupImagePayload(array $overrides = []): array
{
    return array_replace_recursive([
        'type' => 'ReceivedCallback',
        'instanceId' => 'INSTANCE-INTAKE-001',
        'messageId' => '2AD10D322A6206EB17B4',
        'momment' => '2026-04-05T17:21:55-03:00',
        'status' => 'RECEIVED',
        'fromMe' => false,
        'isGroup' => true,
        'phone' => '120363425796926861-group',
        'participantPhone' => '554896553954',
        'participantLid' => '18924129272011@lid',
        'connectedPhone' => '5548998483594',
        'chatName' => 'Evento vivo 1',
        'senderName' => 'Anderson Marques',
        'image' => [
            'imageUrl' => 'https://cdn.z-api.io/media/group-image.jpg',
            'caption' => 'Teste de grupo',
            'mimeType' => 'image/jpeg',
        ],
    ], $overrides);
}

function makeZapiPrivateTextPayload(string $text, array $overrides = []): array
{
    return array_replace_recursive([
        'type' => 'ReceivedCallback',
        'instanceId' => 'INSTANCE-INTAKE-001',
        'messageId' => '3EB0689AF3EAE352EC526D',
        'momment' => '2026-04-05T17:39:47-03:00',
        'status' => 'RECEIVED',
        'fromMe' => false,
        'isGroup' => false,
        'phone' => '5548996553954',
        'connectedPhone' => '5548998483594',
        'senderName' => 'Cliente Teste',
        'text' => [
            'message' => $text,
        ],
    ], $overrides);
}

function makeZapiPrivateImagePayload(array $overrides = []): array
{
    return array_replace_recursive([
        'type' => 'ReceivedCallback',
        'instanceId' => 'INSTANCE-INTAKE-001',
        'messageId' => '3EB0B1E03BB6FAEACB6BC8',
        'momment' => '2026-04-05T17:43:15-03:00',
        'status' => 'RECEIVED',
        'fromMe' => false,
        'isGroup' => false,
        'phone' => '5548996553954',
        'connectedPhone' => '5548998483594',
        'senderName' => 'Cliente Teste',
        'image' => [
            'imageUrl' => 'https://cdn.z-api.io/media/private-image.jpg',
            'caption' => 'Foto privada',
            'mimeType' => 'image/jpeg',
        ],
    ], $overrides);
}

function makeZapiPrivateVideoPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'type' => 'ReceivedCallback',
        'instanceId' => 'INSTANCE-INTAKE-001',
        'messageId' => '3EB0VIDEOFAEACB6BC8',
        'momment' => '2026-04-08T08:15:00-03:00',
        'status' => 'RECEIVED',
        'fromMe' => false,
        'isGroup' => false,
        'phone' => '5548996553954',
        'connectedPhone' => '5548998483594',
        'senderName' => 'Cliente Teste',
        'photo' => 'https://cdn.z-api.io/avatars/private-avatar.jpg',
        'video' => [
            'videoUrl' => 'https://cdn.z-api.io/media/private-video.mp4',
            'caption' => 'Video privado',
            'mimeType' => 'video/mp4',
        ],
    ], $overrides);
}

function makeZapiPrivateStickerPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'type' => 'ReceivedCallback',
        'instanceId' => 'INSTANCE-INTAKE-001',
        'messageId' => '3EB0STICKERFAEACB6BC8',
        'momment' => '2026-04-08T08:16:00-03:00',
        'status' => 'RECEIVED',
        'fromMe' => false,
        'isGroup' => false,
        'phone' => '5548996553954',
        'connectedPhone' => '5548998483594',
        'senderName' => 'Cliente Teste',
        'sticker' => [
            'stickerUrl' => 'https://cdn.z-api.io/media/private-sticker.webp',
            'mimeType' => 'image/webp',
        ],
    ], $overrides);
}

function makeZapiPrivateAudioPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'type' => 'ReceivedCallback',
        'instanceId' => 'INSTANCE-INTAKE-001',
        'messageId' => '3EB0AUDIOFAEACB6BC8',
        'momment' => '2026-04-08T08:17:00-03:00',
        'status' => 'RECEIVED',
        'fromMe' => false,
        'isGroup' => false,
        'phone' => '5548996553954',
        'connectedPhone' => '5548998483594',
        'senderName' => 'Cliente Teste',
        'audio' => [
            'audioUrl' => 'https://cdn.z-api.io/media/private-audio.ogg',
            'mimeType' => 'audio/ogg; codecs=opus',
            'ptt' => true,
            'seconds' => 2,
        ],
    ], $overrides);
}

function makeChannelEntitlements(array $overrides = []): array
{
    return array_replace_recursive([
        'channels' => [
            'whatsapp_groups' => [
                'enabled' => true,
                'max' => 5,
            ],
            'whatsapp_direct' => [
                'enabled' => true,
            ],
            'public_upload' => [
                'enabled' => false,
            ],
            'telegram' => [
                'enabled' => false,
            ],
            'blacklist' => [
                'enabled' => false,
            ],
            'whatsapp' => [
                'shared_instance' => [
                    'enabled' => true,
                ],
                'dedicated_instance' => [
                    'enabled' => false,
                    'max_per_event' => 0,
                ],
                'feedback' => [
                    'reject_reply' => [
                        'enabled' => false,
                        'message' => null,
                    ],
                ],
            ],
        ],
    ], $overrides);
}

function enableEventModule(Event $event, string $moduleKey, bool $enabled = true): void
{
    $event->modules()->updateOrCreate(
        ['module_key' => $moduleKey],
        ['is_enabled' => $enabled],
    );
}

function createWhatsAppGroupChannel(Event $event): EventChannel
{
    return EventChannel::query()->create([
        'event_id' => $event->id,
        'channel_type' => ChannelType::WhatsAppGroup->value,
        'provider' => 'zapi',
        'label' => 'WhatsApp grupos',
        'status' => 'active',
        'config_json' => [
            'groups' => [],
        ],
    ]);
}

function createWhatsAppDirectChannel(Event $event, string $code = 'ANAEJOAO', int $ttlMinutes = 180): EventChannel
{
    return EventChannel::query()->create([
        'event_id' => $event->id,
        'channel_type' => ChannelType::WhatsAppDirect->value,
        'provider' => 'zapi',
        'external_id' => $code,
        'label' => 'WhatsApp direto',
        'status' => 'active',
        'config_json' => [
            'media_inbox_code' => $code,
            'session_ttl_minutes' => $ttlMinutes,
        ],
    ]);
}

it('routes bound group media to the inbound media pipeline only when the event intake is commercially eligible', function () {
    Bus::fake([ProcessInboundMediaWebhookJob::class, SendWhatsAppMessageJob::class]);

    $instance = WhatsAppInstance::factory()->connected()->create([
        'external_instance_id' => 'INSTANCE-INTAKE-001',
    ]);

    $event = Event::factory()->create([
        'organization_id' => $instance->organization_id,
        'status' => EventStatus::Active->value,
        'default_whatsapp_instance_id' => $instance->id,
        'whatsapp_instance_mode' => 'shared',
        'current_entitlements_json' => makeChannelEntitlements(),
    ]);

    enableEventModule($event, 'live');
    $groupChannel = createWhatsAppGroupChannel($event);

    WhatsAppGroupBinding::query()->create([
        'organization_id' => $event->organization_id,
        'event_id' => $event->id,
        'instance_id' => $instance->id,
        'group_external_id' => '120363425796926861-group',
        'group_name' => 'Evento vivo 1',
        'binding_type' => GroupBindingType::EventGallery->value,
        'is_active' => true,
        'metadata_json' => [],
    ]);

    $response = $this->postJson(
        "/api/v1/webhooks/whatsapp/zapi/{$instance->external_instance_id}/inbound",
        makeZapiGroupImagePayload(),
    );

    $response->assertOk()->assertJson(['status' => 'received']);

    Bus::assertDispatched(ProcessInboundMediaWebhookJob::class, function (ProcessInboundMediaWebhookJob $job) use ($event, $groupChannel) {
        return $job->provider === 'zapi'
            && data_get($job->payload, '_event_context.event_id') === $event->id
            && data_get($job->payload, '_event_context.event_channel_id') === $groupChannel->id
            && data_get($job->payload, '_event_context.intake_source') === 'whatsapp_group'
            && data_get($job->payload, '_event_context.group_external_id') === '120363425796926861-group'
            && data_get($job->payload, '_event_context.provider_message_id') === '2AD10D322A6206EB17B4';
    });
});

it('ignores group media for event intake when the event is not eligible for whatsapp groups', function () {
    Bus::fake([ProcessInboundMediaWebhookJob::class]);

    $instance = WhatsAppInstance::factory()->connected()->create([
        'external_instance_id' => 'INSTANCE-INTAKE-001',
    ]);

    $event = Event::factory()->create([
        'organization_id' => $instance->organization_id,
        'status' => EventStatus::Draft->value,
        'default_whatsapp_instance_id' => $instance->id,
        'whatsapp_instance_mode' => 'shared',
        'current_entitlements_json' => makeChannelEntitlements([
            'channels' => [
                'whatsapp_groups' => [
                    'enabled' => false,
                ],
            ],
        ]),
    ]);

    enableEventModule($event, 'live');
    createWhatsAppGroupChannel($event);

    WhatsAppGroupBinding::query()->create([
        'organization_id' => $event->organization_id,
        'event_id' => $event->id,
        'instance_id' => $instance->id,
        'group_external_id' => '120363425796926861-group',
        'group_name' => 'Evento vivo 1',
        'binding_type' => GroupBindingType::EventGallery->value,
        'is_active' => true,
        'metadata_json' => [],
    ]);

    $response = $this->postJson(
        "/api/v1/webhooks/whatsapp/zapi/{$instance->external_instance_id}/inbound",
        makeZapiGroupImagePayload(),
    );

    $response->assertOk()->assertJson(['status' => 'received']);

    Bus::assertNotDispatched(ProcessInboundMediaWebhookJob::class);
});

it('ignores a real anonymized unbound group webhook for event intake without creating inbound media records', function () {
    Bus::fake([ProcessInboundMediaWebhookJob::class, SendWhatsAppMessageJob::class]);

    $instance = WhatsAppInstance::factory()->connected()->create([
        'external_instance_id' => 'INSTANCE-FIXTURE-001',
    ]);

    $event = Event::factory()->create([
        'organization_id' => $instance->organization_id,
        'status' => EventStatus::Active->value,
        'default_whatsapp_instance_id' => $instance->id,
        'whatsapp_instance_mode' => 'shared',
        'current_entitlements_json' => makeChannelEntitlements(),
    ]);

    enableEventModule($event, 'live');
    createWhatsAppGroupChannel($event);

    $response = $this->postJson(
        "/api/v1/webhooks/whatsapp/zapi/{$instance->external_instance_id}/inbound",
        loadZApiIntakeFixture('group-image-with-caption'),
    );

    $response->assertOk()->assertJson(['status' => 'received']);

    Bus::assertNotDispatched(ProcessInboundMediaWebhookJob::class);

    expect(InboundMessage::query()->count())->toBe(0)
        ->and(
            WhatsAppMessage::query()
                ->where('direction', MessageDirection::Outbound)
                ->count()
        )->toBe(0);
});

it('opens a direct intake session when a valid media inbox code is received in a private chat', function () {
    Bus::fake([SendWhatsAppMessageJob::class]);

    $instance = WhatsAppInstance::factory()->connected()->create([
        'external_instance_id' => 'INSTANCE-INTAKE-001',
    ]);

    $event = Event::factory()->create([
        'organization_id' => $instance->organization_id,
        'status' => EventStatus::Active->value,
        'default_whatsapp_instance_id' => $instance->id,
        'whatsapp_instance_mode' => 'shared',
        'current_entitlements_json' => makeChannelEntitlements(),
    ]);

    enableEventModule($event, 'live');
    createWhatsAppDirectChannel($event, 'ANAEJOAO', 180);

    $response = $this->postJson(
        "/api/v1/webhooks/whatsapp/zapi/{$instance->external_instance_id}/inbound",
        makeZapiPrivateTextPayload('#ANAEJOAO'),
    );

    $response->assertOk()->assertJson(['status' => 'received']);

    $session = WhatsAppInboxSession::query()->sole();
    $outboundReply = WhatsAppMessage::query()
        ->where('direction', MessageDirection::Outbound)
        ->sole();

    expect($session->event_id)->toBe($event->id)
        ->and($session->instance_id)->toBe($instance->id)
        ->and($session->sender_external_id)->toBe('5548996553954')
        ->and($session->status)->toBe('active')
        ->and($session->activated_by_provider_message_id)->toBe('3EB0689AF3EAE352EC526D')
        ->and($session->expires_at)->not->toBeNull();

    expect($outboundReply->reply_to_provider_message_id)->toBe('3EB0689AF3EAE352EC526D')
        ->and($outboundReply->recipient_phone)->toBe('5548996553954')
        ->and(data_get($outboundReply->payload_json, 'messageId'))->toBe('3EB0689AF3EAE352EC526D')
        ->and($outboundReply->text_body)->toContain($event->title)
        ->and($outboundReply->text_body)->toContain('Sair');
});

it('replies that the sender is already linked when the same direct intake code is sent during an active session', function () {
    Bus::fake([SendWhatsAppMessageJob::class]);

    $instance = WhatsAppInstance::factory()->connected()->create([
        'external_instance_id' => 'INSTANCE-INTAKE-001',
    ]);

    $event = Event::factory()->create([
        'organization_id' => $instance->organization_id,
        'status' => EventStatus::Active->value,
        'default_whatsapp_instance_id' => $instance->id,
        'whatsapp_instance_mode' => 'shared',
        'current_entitlements_json' => makeChannelEntitlements(),
    ]);

    enableEventModule($event, 'live');
    $directChannel = createWhatsAppDirectChannel($event, 'ANAEJOAO', 180);

    $session = WhatsAppInboxSession::query()->create([
        'organization_id' => $event->organization_id,
        'event_id' => $event->id,
        'event_channel_id' => $directChannel->id,
        'instance_id' => $instance->id,
        'sender_external_id' => '5548996553954',
        'sender_phone' => '5548996553954',
        'chat_external_id' => '5548996553954',
        'status' => 'active',
        'activated_by_provider_message_id' => '3EB0689AF3EAE352EC526D',
        'last_inbound_provider_message_id' => '3EB0689AF3EAE352EC526D',
        'activated_at' => now()->subMinute(),
        'last_interaction_at' => now()->subMinute(),
        'expires_at' => now()->addMinutes(20),
    ]);

    $response = $this->postJson(
        "/api/v1/webhooks/whatsapp/zapi/{$instance->external_instance_id}/inbound",
        makeZapiPrivateTextPayload('ANAEJOAO', [
            'messageId' => '3EB0ALREADYACTIVE',
        ]),
    );

    $response->assertOk()->assertJson(['status' => 'received']);

    $session->refresh();
    $outboundReply = WhatsAppMessage::query()
        ->where('direction', MessageDirection::Outbound)
        ->sole();

    expect($session->status)->toBe('active')
        ->and($session->last_inbound_provider_message_id)->toBe('3EB0ALREADYACTIVE')
        ->and($session->expires_at?->greaterThan(now()->addMinutes(170)))->toBeTrue()
        ->and($outboundReply->reply_to_provider_message_id)->toBe('3EB0ALREADYACTIVE')
        ->and($outboundReply->text_body)->toContain('ja esta vinculado')
        ->and($outboundReply->text_body)->toContain($event->title);
});

it('ignores group intake when the configured instance is in dedicated conflict with another event', function () {
    Bus::fake([ProcessInboundMediaWebhookJob::class, SendWhatsAppMessageJob::class]);

    $instance = WhatsAppInstance::factory()->connected()->create([
        'external_instance_id' => 'INSTANCE-FIXTURE-001',
    ]);

    $entitlements = makeChannelEntitlements([
        'channels' => [
            'whatsapp' => [
                'shared_instance' => [
                    'enabled' => false,
                ],
                'dedicated_instance' => [
                    'enabled' => true,
                    'max_per_event' => 1,
                ],
            ],
        ],
    ]);

    $ownerEvent = Event::factory()->create([
        'organization_id' => $instance->organization_id,
        'status' => EventStatus::Active->value,
        'default_whatsapp_instance_id' => $instance->id,
        'whatsapp_instance_mode' => 'dedicated',
        'current_entitlements_json' => $entitlements,
    ]);

    enableEventModule($ownerEvent, 'live');
    createWhatsAppGroupChannel($ownerEvent);

    $conflictingEvent = Event::factory()->create([
        'organization_id' => $instance->organization_id,
        'status' => EventStatus::Active->value,
        'default_whatsapp_instance_id' => $instance->id,
        'whatsapp_instance_mode' => 'dedicated',
        'current_entitlements_json' => $entitlements,
    ]);

    enableEventModule($conflictingEvent, 'live');
    createWhatsAppGroupChannel($conflictingEvent);

    WhatsAppGroupBinding::query()->create([
        'organization_id' => $conflictingEvent->organization_id,
        'event_id' => $conflictingEvent->id,
        'instance_id' => $instance->id,
        'group_external_id' => '120363499999999999-group',
        'group_name' => 'Grupo Fixture Evento',
        'binding_type' => GroupBindingType::EventGallery->value,
        'is_active' => true,
        'metadata_json' => [],
    ]);

    $response = $this->postJson(
        "/api/v1/webhooks/whatsapp/zapi/{$instance->external_instance_id}/inbound",
        loadZApiIntakeFixture('group-image-with-caption'),
    );

    $response->assertOk()->assertJson(['status' => 'received']);

    Bus::assertNotDispatched(ProcessInboundMediaWebhookJob::class);

    expect(InboundMessage::query()->count())->toBe(0)
        ->and(
            WhatsAppMessage::query()
                ->where('direction', MessageDirection::Outbound)
                ->count()
        )->toBe(0);
});

it('routes private media to the inbound media pipeline when a direct intake session is active', function () {
    Bus::fake([ProcessInboundMediaWebhookJob::class, SendWhatsAppMessageJob::class]);

    $instance = WhatsAppInstance::factory()->connected()->create([
        'external_instance_id' => 'INSTANCE-INTAKE-001',
    ]);

    $event = Event::factory()->create([
        'organization_id' => $instance->organization_id,
        'status' => EventStatus::Active->value,
        'default_whatsapp_instance_id' => $instance->id,
        'whatsapp_instance_mode' => 'shared',
        'current_entitlements_json' => makeChannelEntitlements(),
    ]);

    enableEventModule($event, 'live');
    $directChannel = createWhatsAppDirectChannel($event, 'ANAEJOAO', 180);

    WhatsAppInboxSession::query()->create([
        'organization_id' => $event->organization_id,
        'event_id' => $event->id,
        'event_channel_id' => $directChannel->id,
        'instance_id' => $instance->id,
        'sender_external_id' => '5548996553954',
        'sender_phone' => '5548996553954',
        'chat_external_id' => '5548996553954',
        'status' => 'active',
        'activated_by_provider_message_id' => '3EB0689AF3EAE352EC526D',
        'last_inbound_provider_message_id' => '3EB0689AF3EAE352EC526D',
        'activated_at' => now()->subMinute(),
        'last_interaction_at' => now()->subMinute(),
        'expires_at' => now()->addMinutes(180),
    ]);

    $response = $this->postJson(
        "/api/v1/webhooks/whatsapp/zapi/{$instance->external_instance_id}/inbound",
        makeZapiPrivateImagePayload(),
    );

    $response->assertOk()->assertJson(['status' => 'received']);

    Bus::assertDispatched(ProcessInboundMediaWebhookJob::class, function (ProcessInboundMediaWebhookJob $job) use ($event, $directChannel) {
        return data_get($job->payload, '_event_context.event_id') === $event->id
            && data_get($job->payload, '_event_context.event_channel_id') === $directChannel->id
            && data_get($job->payload, '_event_context.intake_source') === 'whatsapp_direct'
            && data_get($job->payload, '_event_context.provider_message_id') === '3EB0B1E03BB6FAEACB6BC8';
    });
});

it('routes private video to the inbound media pipeline with explicit video metadata', function () {
    Bus::fake([ProcessInboundMediaWebhookJob::class, SendWhatsAppMessageJob::class]);

    $instance = WhatsAppInstance::factory()->connected()->create([
        'external_instance_id' => 'INSTANCE-INTAKE-001',
    ]);

    $event = Event::factory()->create([
        'organization_id' => $instance->organization_id,
        'status' => EventStatus::Active->value,
        'default_whatsapp_instance_id' => $instance->id,
        'whatsapp_instance_mode' => 'shared',
        'current_entitlements_json' => makeChannelEntitlements(),
    ]);

    enableEventModule($event, 'live');
    $directChannel = createWhatsAppDirectChannel($event, 'ANAEJOAO', 180);

    WhatsAppInboxSession::query()->create([
        'organization_id' => $event->organization_id,
        'event_id' => $event->id,
        'event_channel_id' => $directChannel->id,
        'instance_id' => $instance->id,
        'sender_external_id' => '5548996553954',
        'sender_phone' => '5548996553954',
        'chat_external_id' => '5548996553954',
        'status' => 'active',
        'activated_by_provider_message_id' => '3EB0689AF3EAE352EC526D',
        'last_inbound_provider_message_id' => '3EB0689AF3EAE352EC526D',
        'activated_at' => now()->subMinute(),
        'last_interaction_at' => now()->subMinute(),
        'expires_at' => now()->addMinutes(180),
    ]);

    $response = $this->postJson(
        "/api/v1/webhooks/whatsapp/zapi/{$instance->external_instance_id}/inbound",
        makeZapiPrivateVideoPayload(),
    );

    $response->assertOk()->assertJson(['status' => 'received']);

    Bus::assertDispatched(ProcessInboundMediaWebhookJob::class, function (ProcessInboundMediaWebhookJob $job) use ($event, $directChannel) {
        return data_get($job->payload, '_event_context.event_id') === $event->id
            && data_get($job->payload, '_event_context.event_channel_id') === $directChannel->id
            && data_get($job->payload, '_event_context.intake_source') === 'whatsapp_direct'
            && data_get($job->payload, 'message_type') === 'video'
            && data_get($job->payload, 'mime_type') === 'video/mp4'
            && data_get($job->payload, '_event_context.media_url') === 'https://cdn.z-api.io/media/private-video.mp4'
            && data_get($job->payload, '_event_context.provider_message_id') === '3EB0VIDEOFAEACB6BC8';
    });
});

it('does not route stickers to the inbound media pipeline even when a direct intake session is active', function () {
    Bus::fake([ProcessInboundMediaWebhookJob::class, SendWhatsAppMessageJob::class]);

    $instance = WhatsAppInstance::factory()->connected()->create([
        'external_instance_id' => 'INSTANCE-INTAKE-001',
    ]);

    $event = Event::factory()->create([
        'organization_id' => $instance->organization_id,
        'status' => EventStatus::Active->value,
        'default_whatsapp_instance_id' => $instance->id,
        'whatsapp_instance_mode' => 'shared',
        'current_entitlements_json' => makeChannelEntitlements(),
    ]);

    enableEventModule($event, 'live');
    $directChannel = createWhatsAppDirectChannel($event, 'ANAEJOAO', 180);

    WhatsAppInboxSession::query()->create([
        'organization_id' => $event->organization_id,
        'event_id' => $event->id,
        'event_channel_id' => $directChannel->id,
        'instance_id' => $instance->id,
        'sender_external_id' => '5548996553954',
        'sender_phone' => '5548996553954',
        'chat_external_id' => '5548996553954',
        'status' => 'active',
        'activated_by_provider_message_id' => '3EB0689AF3EAE352EC526D',
        'last_inbound_provider_message_id' => '3EB0689AF3EAE352EC526D',
        'activated_at' => now()->subMinute(),
        'last_interaction_at' => now()->subMinute(),
        'expires_at' => now()->addMinutes(180),
    ]);

    $response = $this->postJson(
        "/api/v1/webhooks/whatsapp/zapi/{$instance->external_instance_id}/inbound",
        makeZapiPrivateStickerPayload(),
    );

    $response->assertOk()->assertJson(['status' => 'received']);

    Bus::assertNotDispatched(ProcessInboundMediaWebhookJob::class);

    expect(
        WhatsAppMessage::query()
            ->where('direction', MessageDirection::Outbound)
            ->count()
    )->toBe(0);
});

it('routes private audio to the inbound capture pipeline when a direct intake session is active', function () {
    Bus::fake([ProcessInboundMediaWebhookJob::class, SendWhatsAppMessageJob::class]);

    $instance = WhatsAppInstance::factory()->connected()->create([
        'external_instance_id' => 'INSTANCE-INTAKE-001',
    ]);

    $event = Event::factory()->create([
        'organization_id' => $instance->organization_id,
        'status' => EventStatus::Active->value,
        'default_whatsapp_instance_id' => $instance->id,
        'whatsapp_instance_mode' => 'shared',
        'current_entitlements_json' => makeChannelEntitlements(),
    ]);

    enableEventModule($event, 'live');
    $directChannel = createWhatsAppDirectChannel($event, 'ANAEJOAO', 180);

    WhatsAppInboxSession::query()->create([
        'organization_id' => $event->organization_id,
        'event_id' => $event->id,
        'event_channel_id' => $directChannel->id,
        'instance_id' => $instance->id,
        'sender_external_id' => '5548996553954',
        'sender_phone' => '5548996553954',
        'chat_external_id' => '5548996553954',
        'status' => 'active',
        'activated_by_provider_message_id' => '3EB0689AF3EAE352EC526D',
        'last_inbound_provider_message_id' => '3EB0689AF3EAE352EC526D',
        'activated_at' => now()->subMinute(),
        'last_interaction_at' => now()->subMinute(),
        'expires_at' => now()->addMinutes(180),
    ]);

    $response = $this->postJson(
        "/api/v1/webhooks/whatsapp/zapi/{$instance->external_instance_id}/inbound",
        makeZapiPrivateAudioPayload(),
    );

    $response->assertOk()->assertJson(['status' => 'received']);

    Bus::assertDispatched(ProcessInboundMediaWebhookJob::class, function (ProcessInboundMediaWebhookJob $job) use ($event, $directChannel) {
        return data_get($job->payload, '_event_context.event_id') === $event->id
            && data_get($job->payload, '_event_context.event_channel_id') === $directChannel->id
            && data_get($job->payload, '_event_context.intake_source') === 'whatsapp_direct'
            && data_get($job->payload, '_event_context.capture_target') === 'event_audio'
            && data_get($job->payload, 'message_type') === 'audio'
            && data_get($job->payload, 'mime_type') === 'audio/ogg; codecs=opus'
            && data_get($job->payload, '_event_context.media_url') === 'https://cdn.z-api.io/media/private-audio.ogg'
            && data_get($job->payload, '_event_context.provider_message_id') === '3EB0AUDIOFAEACB6BC8';
    });
});

it('replies with reactivation instructions when private media arrives after the intake session expires', function () {
    Cache::flush();
    Bus::fake([ProcessInboundMediaWebhookJob::class, SendWhatsAppMessageJob::class]);

    $instance = WhatsAppInstance::factory()->connected()->create([
        'external_instance_id' => 'INSTANCE-INTAKE-001',
    ]);

    $event = Event::factory()->create([
        'organization_id' => $instance->organization_id,
        'status' => EventStatus::Active->value,
        'default_whatsapp_instance_id' => $instance->id,
        'whatsapp_instance_mode' => 'shared',
        'current_entitlements_json' => makeChannelEntitlements(),
    ]);

    enableEventModule($event, 'live');
    $directChannel = createWhatsAppDirectChannel($event, 'ANAEJOAO', 180);

    WhatsAppInboxSession::query()->create([
        'organization_id' => $event->organization_id,
        'event_id' => $event->id,
        'event_channel_id' => $directChannel->id,
        'instance_id' => $instance->id,
        'sender_external_id' => '5548996553954',
        'sender_phone' => '5548996553954',
        'chat_external_id' => '5548996553954',
        'status' => 'active',
        'activated_by_provider_message_id' => '3EB0689AF3EAE352EC526D',
        'last_inbound_provider_message_id' => '3EB0689AF3EAE352EC526D',
        'activated_at' => now()->subHours(4),
        'last_interaction_at' => now()->subHours(4),
        'expires_at' => now()->subMinute(),
    ]);

    $response = $this->postJson(
        "/api/v1/webhooks/whatsapp/zapi/{$instance->external_instance_id}/inbound",
        makeZapiPrivateVideoPayload(),
    );

    $response->assertOk()->assertJson(['status' => 'received']);

    Bus::assertNotDispatched(ProcessInboundMediaWebhookJob::class);

    $reply = WhatsAppMessage::query()
        ->where('direction', MessageDirection::Outbound)
        ->sole();

    expect($reply->recipient_phone)->toBe('5548996553954')
        ->and($reply->reply_to_provider_message_id)->toBe('3EB0VIDEOFAEACB6BC8')
        ->and($reply->text_body)->toContain($event->title)
        ->and($reply->text_body)->toContain('ANAEJOAO novamente');
});

it('does not spam repeated reactivation instructions for consecutive private media after session expiry', function () {
    Cache::flush();
    Bus::fake([ProcessInboundMediaWebhookJob::class, SendWhatsAppMessageJob::class]);

    $instance = WhatsAppInstance::factory()->connected()->create([
        'external_instance_id' => 'INSTANCE-INTAKE-001',
    ]);

    $event = Event::factory()->create([
        'organization_id' => $instance->organization_id,
        'status' => EventStatus::Active->value,
        'default_whatsapp_instance_id' => $instance->id,
        'whatsapp_instance_mode' => 'shared',
        'current_entitlements_json' => makeChannelEntitlements(),
    ]);

    enableEventModule($event, 'live');
    $directChannel = createWhatsAppDirectChannel($event, 'ANAEJOAO', 180);

    WhatsAppInboxSession::query()->create([
        'organization_id' => $event->organization_id,
        'event_id' => $event->id,
        'event_channel_id' => $directChannel->id,
        'instance_id' => $instance->id,
        'sender_external_id' => '5548996553954',
        'sender_phone' => '5548996553954',
        'chat_external_id' => '5548996553954',
        'status' => 'active',
        'activated_by_provider_message_id' => '3EB0689AF3EAE352EC526D',
        'last_inbound_provider_message_id' => '3EB0689AF3EAE352EC526D',
        'activated_at' => now()->subHours(4),
        'last_interaction_at' => now()->subHours(4),
        'expires_at' => now()->subMinute(),
    ]);

    $first = $this->postJson(
        "/api/v1/webhooks/whatsapp/zapi/{$instance->external_instance_id}/inbound",
        makeZapiPrivateVideoPayload([
            'messageId' => '3EB0VIDEOFIRST',
        ]),
    );

    $second = $this->postJson(
        "/api/v1/webhooks/whatsapp/zapi/{$instance->external_instance_id}/inbound",
        makeZapiPrivateVideoPayload([
            'messageId' => '3EB0VIDEOSECOND',
        ]),
    );

    $first->assertOk()->assertJson(['status' => 'received']);
    $second->assertOk()->assertJson(['status' => 'received']);

    Bus::assertNotDispatched(ProcessInboundMediaWebhookJob::class);

    expect(
        WhatsAppMessage::query()
            ->where('direction', MessageDirection::Outbound)
            ->count()
    )->toBe(1);
});

it('closes a direct intake session when the sender types sair in the private chat', function () {
    Bus::fake([SendWhatsAppMessageJob::class]);

    $instance = WhatsAppInstance::factory()->connected()->create([
        'external_instance_id' => 'INSTANCE-INTAKE-001',
    ]);

    $event = Event::factory()->create([
        'organization_id' => $instance->organization_id,
        'status' => EventStatus::Active->value,
        'default_whatsapp_instance_id' => $instance->id,
        'whatsapp_instance_mode' => 'shared',
        'current_entitlements_json' => makeChannelEntitlements(),
    ]);

    enableEventModule($event, 'live');
    $directChannel = createWhatsAppDirectChannel($event, 'ANAEJOAO', 180);

    $session = WhatsAppInboxSession::query()->create([
        'organization_id' => $event->organization_id,
        'event_id' => $event->id,
        'event_channel_id' => $directChannel->id,
        'instance_id' => $instance->id,
        'sender_external_id' => '5548996553954',
        'sender_phone' => '5548996553954',
        'chat_external_id' => '5548996553954',
        'status' => 'active',
        'activated_by_provider_message_id' => '3EB0689AF3EAE352EC526D',
        'last_inbound_provider_message_id' => '3EB0689AF3EAE352EC526D',
        'activated_at' => now()->subMinutes(10),
        'last_interaction_at' => now()->subMinute(),
        'expires_at' => now()->addMinutes(170),
    ]);

    $response = $this->postJson(
        "/api/v1/webhooks/whatsapp/zapi/{$instance->external_instance_id}/inbound",
        makeZapiPrivateTextPayload('Sair', [
            'messageId' => '3EB009E4C06F9AD5E48A9D',
        ]),
    );

    $response->assertOk()->assertJson(['status' => 'received']);

    $session->refresh();
    $outboundReply = WhatsAppMessage::query()
        ->where('direction', MessageDirection::Outbound)
        ->sole();

    expect($session->status)->toBe('closed')
        ->and($session->closed_at)->not->toBeNull()
        ->and($session->last_inbound_provider_message_id)->toBe('3EB009E4C06F9AD5E48A9D');

    expect($outboundReply->reply_to_provider_message_id)->toBe('3EB009E4C06F9AD5E48A9D')
        ->and(data_get($outboundReply->payload_json, 'messageId'))->toBe('3EB009E4C06F9AD5E48A9D')
        ->and($outboundReply->text_body)->toContain('encerrada');
});
