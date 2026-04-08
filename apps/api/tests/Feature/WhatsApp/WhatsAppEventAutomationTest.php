<?php

use App\Modules\Channels\Enums\ChannelType;
use App\Modules\Channels\Models\EventChannel;
use App\Modules\Events\Enums\EventStatus;
use App\Modules\Events\Models\Event;
use App\Modules\InboundMedia\Models\InboundMessage;
use App\Modules\InboundMedia\Jobs\ProcessInboundWebhookJob as ProcessInboundMediaWebhookJob;
use App\Modules\MediaIntelligence\DTOs\VisualReasoningEvaluationResult;
use App\Modules\MediaIntelligence\Jobs\EvaluateMediaPromptJob;
use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;
use App\Modules\MediaIntelligence\Services\VisualReasoningProviderInterface;
use App\Modules\MediaProcessing\Events\MediaPublished;
use App\Modules\MediaProcessing\Events\MediaRejected;
use App\Modules\MediaProcessing\Jobs\RunModerationJob;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Models\EventMediaVariant;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\WhatsApp\Enums\GroupBindingType;
use App\Modules\WhatsApp\Enums\MessageDirection;
use App\Modules\WhatsApp\Jobs\SendWhatsAppMessageJob;
use App\Modules\WhatsApp\Listeners\SendFeedbackOnMediaPublished;
use App\Modules\WhatsApp\Listeners\SendFeedbackOnMediaRejected;
use App\Modules\WhatsApp\Models\WhatsAppGroupBinding;
use App\Modules\WhatsApp\Models\WhatsAppInboxSession;
use App\Modules\WhatsApp\Models\WhatsAppInboundEvent;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use App\Modules\WhatsApp\Models\WhatsAppMessageFeedback;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

function loadZApiAutomationFixture(string $name, array $overrides = []): array
{
    $payload = json_decode(
        file_get_contents(base_path("tests/Fixtures/WhatsApp/ZApi/{$name}.json")),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    return array_replace_recursive($payload, $overrides);
}

function makeAutomationEntitlements(array $overrides = []): array
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
                'enabled' => true,
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
                        'enabled' => true,
                        'message' => 'Sua midia nao segue as diretrizes do evento. 🛡️',
                    ],
                ],
            ],
        ],
    ], $overrides);
}

function enableAutomationEventModule(Event $event, string $moduleKey = 'live'): void
{
    $event->modules()->updateOrCreate(
        ['module_key' => $moduleKey],
        ['is_enabled' => true],
    );
}

/**
 * @return array<string, mixed>
 */
function makeInboundEventContextPayload(Event $event, EventChannel $channel, array $overrides = []): array
{
    return array_replace_recursive(
        loadZApiAutomationFixture('group-image-with-caption'),
        [
            '_event_context' => [
                'event_id' => $event->id,
                'event_channel_id' => $channel->id,
                'trace_id' => 'trace-whatsapp-feedback-001',
                'intake_source' => 'whatsapp_group',
                'provider_message_id' => '2A20028071DA23E04188',
                'chat_external_id' => '120363499999999999-group',
                'group_external_id' => '120363499999999999-group',
                'sender_external_id' => '11111111111111@lid',
                'sender_phone' => '554899991111',
                'sender_lid' => '11111111111111@lid',
                'sender_name' => 'Participante Fixture',
                'caption' => 'Teste de grupo',
                'media_url' => 'https://cdn.fixture.test/zapi/group-image-with-caption.jpg',
            ],
        ],
        $overrides,
    );
}

/**
 * @return array{0: InboundMessage, 1: EventMedia, 2: array<string, mixed>}
 */
function createInboundMediaWithEventContext(Event $event, EventChannel $channel): array
{
    $payload = makeInboundEventContextPayload($event, $channel);

    $inboundMessage = InboundMessage::query()->create([
        'event_id' => $event->id,
        'event_channel_id' => $channel->id,
        'provider' => 'zapi',
        'message_id' => data_get($payload, '_event_context.provider_message_id'),
        'message_type' => 'image',
        'sender_phone' => data_get($payload, '_event_context.sender_phone'),
        'sender_name' => data_get($payload, '_event_context.sender_name'),
        'body_text' => data_get($payload, '_event_context.caption'),
        'media_url' => data_get($payload, '_event_context.media_url'),
        'normalized_payload_json' => $payload,
        'status' => 'processed',
        'received_at' => now()->subMinute(),
        'processed_at' => now()->subSecond(),
    ]);

    $eventMedia = EventMedia::factory()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $inboundMessage->id,
        'source_type' => 'whatsapp_group',
        'source_label' => 'Participante Fixture',
        'caption' => 'Teste de grupo',
    ]);

    return [$inboundMessage, $eventMedia, $payload];
}

it('auto-binds a group when #ATIVAR#<group_bind_code> is received inside the group', function () {
    Bus::fake([ProcessInboundMediaWebhookJob::class, SendWhatsAppMessageJob::class]);

    $instance = WhatsAppInstance::factory()->connected()->create([
        'external_instance_id' => 'INSTANCE-FIXTURE-001',
    ]);

    $event = Event::factory()->create([
        'organization_id' => $instance->organization_id,
        'status' => EventStatus::Active->value,
        'default_whatsapp_instance_id' => $instance->id,
        'whatsapp_instance_mode' => 'shared',
        'current_entitlements_json' => makeAutomationEntitlements(),
    ]);

    enableAutomationEventModule($event);

    $channel = EventChannel::query()->create([
        'event_id' => $event->id,
        'channel_type' => ChannelType::WhatsAppGroup->value,
        'provider' => 'zapi',
        'external_id' => 'GRUPOAUTO',
        'label' => 'WhatsApp grupos',
        'status' => 'active',
        'config_json' => [
            'group_bind_code' => 'GRUPOAUTO',
            'groups' => [],
        ],
    ]);

    $response = $this->postJson(
        "/api/v1/webhooks/whatsapp/zapi/{$instance->external_instance_id}/inbound",
        loadZApiAutomationFixture('group-text', [
            'text' => ['message' => '#ATIVAR#GRUPOAUTO'],
        ]),
    );

    $response->assertOk()->assertJson(['status' => 'received']);

    $binding = WhatsAppGroupBinding::query()->where('event_id', $event->id)->sole();
    $reply = WhatsAppMessage::query()->where('direction', MessageDirection::Outbound)->sole();

    expect($binding->group_external_id)->toBe('120363499999999999-group')
        ->and($binding->binding_type)->toBe(GroupBindingType::EventGallery)
        ->and($binding->is_active)->toBeTrue();

    expect(data_get($channel->fresh()->config_json, 'groups.0.group_external_id'))
        ->toBe('120363499999999999-group');

    expect($reply->reply_to_provider_message_id)->toBe('2A958180963854924D66')
        ->and($reply->recipient_phone)->toBe('120363499999999999-group')
        ->and($reply->text_body)->toContain($event->title);

    Bus::assertNotDispatched(ProcessInboundMediaWebhookJob::class);
});

it('does not route group media from a blacklisted participant lid and sends negative feedback', function () {
    Bus::fake([ProcessInboundMediaWebhookJob::class, SendWhatsAppMessageJob::class]);

    $instance = WhatsAppInstance::factory()->connected()->create([
        'external_instance_id' => 'INSTANCE-FIXTURE-001',
    ]);

    $event = Event::factory()->create([
        'organization_id' => $instance->organization_id,
        'status' => EventStatus::Active->value,
        'default_whatsapp_instance_id' => $instance->id,
        'whatsapp_instance_mode' => 'shared',
        'current_entitlements_json' => makeAutomationEntitlements(),
    ]);

    enableAutomationEventModule($event);

    EventChannel::query()->create([
        'event_id' => $event->id,
        'channel_type' => ChannelType::WhatsAppGroup->value,
        'provider' => 'zapi',
        'external_id' => 'GRUPOAUTO',
        'label' => 'WhatsApp grupos',
        'status' => 'active',
        'config_json' => [
            'group_bind_code' => 'GRUPOAUTO',
            'groups' => [
                [
                    'group_external_id' => '120363499999999999-group',
                    'group_name' => 'Grupo Fixture Evento',
                    'is_active' => true,
                    'auto_feedback_enabled' => true,
                ],
            ],
        ],
    ]);

    WhatsAppGroupBinding::query()->create([
        'organization_id' => $event->organization_id,
        'event_id' => $event->id,
        'instance_id' => $instance->id,
        'group_external_id' => '120363499999999999-group',
        'group_name' => 'Grupo Fixture Evento',
        'binding_type' => GroupBindingType::EventGallery->value,
        'is_active' => true,
        'metadata_json' => [],
    ]);

    DB::table('event_media_sender_blacklists')->insert([
        'event_id' => $event->id,
        'identity_type' => 'lid',
        'identity_value' => '11111111111111@lid',
        'normalized_phone' => null,
        'reason' => 'blocked for tests',
        'expires_at' => null,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->postJson(
        "/api/v1/webhooks/whatsapp/zapi/{$instance->external_instance_id}/inbound",
        loadZApiAutomationFixture('group-image-with-caption'),
    );

    $response->assertOk()->assertJson(['status' => 'received']);

    Bus::assertNotDispatched(ProcessInboundMediaWebhookJob::class);

    $outbound = WhatsAppMessage::query()
        ->where('direction', MessageDirection::Outbound)
        ->orderBy('id')
        ->get();

    /* expect($feedback->where('feedback_kind', 'reply')->first()?->resolution_json)->toMatchArray([
        'mode' => 'ai',
        'source' => 'vlm',
        'reply_text' => 'Momento de risadas e lembrancas! ðŸ“±ðŸŽ‰',
    ]); */

    /* expect($feedback->where('feedback_kind', 'reply')->first()?->resolution_json)->toMatchArray([
        'mode' => 'ai',
        'source' => 'vlm',
        'reply_text' => 'Momento de risadas e lembrancas! ðŸ“±ðŸŽ‰',
    ]); */

    expect($outbound)->toHaveCount(2)
        ->and($outbound[0]->type->value)->toBe('reaction')
        ->and($outbound[0]->recipient_phone)->toBe('120363499999999999-group')
        ->and($outbound[0]->reply_to_provider_message_id)->toBe('2A20028071DA23E04188')
        ->and($outbound[1]->type->value)->toBe('text')
        ->and($outbound[1]->text_body)->toContain('diretrizes do evento');
});

it('does not route direct media from a blacklisted phone even with an active intake session and sends negative feedback', function () {
    Bus::fake([ProcessInboundMediaWebhookJob::class, SendWhatsAppMessageJob::class]);

    $instance = WhatsAppInstance::factory()->connected()->create([
        'external_instance_id' => 'INSTANCE-FIXTURE-001',
    ]);

    $event = Event::factory()->create([
        'organization_id' => $instance->organization_id,
        'status' => EventStatus::Active->value,
        'default_whatsapp_instance_id' => $instance->id,
        'whatsapp_instance_mode' => 'shared',
        'current_entitlements_json' => makeAutomationEntitlements(),
    ]);

    enableAutomationEventModule($event);

    $channel = EventChannel::query()->create([
        'event_id' => $event->id,
        'channel_type' => ChannelType::WhatsAppDirect->value,
        'provider' => 'zapi',
        'external_id' => 'CODIGODM',
        'label' => 'WhatsApp direto',
        'status' => 'active',
        'config_json' => [
            'media_inbox_code' => 'CODIGODM',
            'session_ttl_minutes' => 180,
        ],
    ]);

    WhatsAppInboxSession::query()->create([
        'organization_id' => $event->organization_id,
        'event_id' => $event->id,
        'event_channel_id' => $channel->id,
        'instance_id' => $instance->id,
        'sender_external_id' => '554899994444',
        'sender_phone' => '554899994444',
        'chat_external_id' => '554899994444',
        'status' => 'active',
        'activated_by_provider_message_id' => 'cf-named-1775391881',
        'last_inbound_provider_message_id' => 'cf-named-1775391881',
        'activated_at' => now()->subMinutes(5),
        'last_interaction_at' => now()->subMinute(),
        'expires_at' => now()->addMinutes(175),
    ]);

    DB::table('event_media_sender_blacklists')->insert([
        'event_id' => $event->id,
        'identity_type' => 'phone',
        'identity_value' => '554899994444',
        'normalized_phone' => '554899994444',
        'reason' => 'blocked for tests',
        'expires_at' => null,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->postJson(
        "/api/v1/webhooks/whatsapp/zapi/{$instance->external_instance_id}/inbound",
        loadZApiAutomationFixture('dm-image-with-caption'),
    );

    $response->assertOk()->assertJson(['status' => 'received']);

    Bus::assertNotDispatched(ProcessInboundMediaWebhookJob::class);

    $outbound = WhatsAppMessage::query()
        ->where('direction', MessageDirection::Outbound)
        ->orderBy('id')
        ->get();

    expect($outbound)->toHaveCount(2)
        ->and($outbound[0]->recipient_phone)->toBe('554899994444')
        ->and($outbound[0]->reply_to_provider_message_id)->toBe('3EB0B1E03BB6FAEACB6BC8')
        ->and($outbound[1]->text_body)->toContain('diretrizes do evento');
});

it('does not open a direct intake session when the sender is blacklisted and replies with blocked feedback', function () {
    Bus::fake([ProcessInboundMediaWebhookJob::class, SendWhatsAppMessageJob::class]);

    $instance = WhatsAppInstance::factory()->connected()->create([
        'external_instance_id' => 'INSTANCE-FIXTURE-001',
    ]);

    $event = Event::factory()->create([
        'organization_id' => $instance->organization_id,
        'status' => EventStatus::Active->value,
        'default_whatsapp_instance_id' => $instance->id,
        'whatsapp_instance_mode' => 'shared',
        'current_entitlements_json' => makeAutomationEntitlements(),
    ]);

    enableAutomationEventModule($event);

    EventChannel::query()->create([
        'event_id' => $event->id,
        'channel_type' => ChannelType::WhatsAppDirect->value,
        'provider' => 'zapi',
        'external_id' => 'CODIGODM',
        'label' => 'WhatsApp direto',
        'status' => 'active',
        'config_json' => [
            'media_inbox_code' => 'CODIGODM',
            'session_ttl_minutes' => 180,
        ],
    ]);

    DB::table('event_media_sender_blacklists')->insert([
        'event_id' => $event->id,
        'identity_type' => 'phone',
        'identity_value' => '554899994444',
        'normalized_phone' => '554899994444',
        'reason' => 'blocked for tests',
        'expires_at' => null,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->postJson(
        "/api/v1/webhooks/whatsapp/zapi/{$instance->external_instance_id}/inbound",
        loadZApiAutomationFixture('dm-text', [
            'text' => ['message' => '#CODIGODM'],
        ]),
    );

    $response->assertOk()->assertJson(['status' => 'received']);

    expect(WhatsAppInboxSession::query()->count())->toBe(0);

    Bus::assertNotDispatched(ProcessInboundMediaWebhookJob::class);

    $outbound = WhatsAppMessage::query()
        ->where('direction', MessageDirection::Outbound)
        ->orderBy('id')
        ->get();

    expect($outbound)->toHaveCount(2)
        ->and($outbound[0]->recipient_phone)->toBe('554899994444')
        ->and($outbound[0]->reply_to_provider_message_id)->toBe('cf-named-1775391881')
        ->and($outbound[1]->text_body)->toContain('diretrizes do evento');
});

it('ignores unknown group bind codes without creating bindings or routing intake', function () {
    Bus::fake([ProcessInboundMediaWebhookJob::class, SendWhatsAppMessageJob::class]);

    $instance = WhatsAppInstance::factory()->connected()->create([
        'external_instance_id' => 'INSTANCE-FIXTURE-001',
    ]);

    $response = $this->postJson(
        "/api/v1/webhooks/whatsapp/zapi/{$instance->external_instance_id}/inbound",
        loadZApiAutomationFixture('group-text', [
            'text' => ['message' => '#ATIVAR#CODIGOINVALIDO'],
        ]),
    );

    $response->assertOk()->assertJson(['status' => 'received']);

    expect(WhatsAppGroupBinding::query()->count())->toBe(0)
        ->and(WhatsAppMessage::query()->where('direction', MessageDirection::Outbound)->count())->toBe(0);

    Bus::assertNotDispatched(ProcessInboundMediaWebhookJob::class);
});

it('queues a detected reaction for eligible media using the chat id instead of the participant phone', function () {
    Bus::fake([ProcessInboundMediaWebhookJob::class, SendWhatsAppMessageJob::class]);

    $instance = WhatsAppInstance::factory()->connected()->create([
        'external_instance_id' => 'INSTANCE-FIXTURE-001',
    ]);

    $event = Event::factory()->create([
        'organization_id' => $instance->organization_id,
        'status' => EventStatus::Active->value,
        'default_whatsapp_instance_id' => $instance->id,
        'whatsapp_instance_mode' => 'shared',
        'current_entitlements_json' => makeAutomationEntitlements(),
    ]);

    enableAutomationEventModule($event);

    EventChannel::query()->create([
        'event_id' => $event->id,
        'channel_type' => ChannelType::WhatsAppGroup->value,
        'provider' => 'zapi',
        'external_id' => 'GRUPOAUTO',
        'label' => 'WhatsApp grupos',
        'status' => 'active',
        'config_json' => [
            'group_bind_code' => 'GRUPOAUTO',
            'groups' => [
                [
                    'group_external_id' => '120363499999999999-group',
                    'group_name' => 'Grupo Fixture Evento',
                    'is_active' => true,
                    'auto_feedback_enabled' => true,
                ],
            ],
        ],
    ]);

    WhatsAppGroupBinding::query()->create([
        'organization_id' => $event->organization_id,
        'event_id' => $event->id,
        'instance_id' => $instance->id,
        'group_external_id' => '120363499999999999-group',
        'group_name' => 'Grupo Fixture Evento',
        'binding_type' => GroupBindingType::EventGallery->value,
        'is_active' => true,
        'metadata_json' => [],
    ]);

    $response = $this->postJson(
        "/api/v1/webhooks/whatsapp/zapi/{$instance->external_instance_id}/inbound",
        loadZApiAutomationFixture('group-image-with-caption'),
    );

    $response->assertOk()->assertJson(['status' => 'received']);

    Bus::assertDispatched(ProcessInboundMediaWebhookJob::class);

    $reaction = WhatsAppMessage::query()
        ->where('direction', MessageDirection::Outbound)
        ->where('type', 'reaction')
        ->sole();

    expect($reaction->recipient_phone)->toBe('120363499999999999-group')
        ->and($reaction->recipient_phone)->not->toBe('554899991111')
        ->and($reaction->reply_to_provider_message_id)->toBe('2A20028071DA23E04188')
        ->and($reaction->text_body)->toBe('⏳');
});

it('propagates the webhook trace id into inbound records and the media routing payload', function () {
    Bus::fake([ProcessInboundMediaWebhookJob::class, SendWhatsAppMessageJob::class]);

    $instance = WhatsAppInstance::factory()->connected()->create([
        'external_instance_id' => 'INSTANCE-FIXTURE-TRACE',
    ]);

    $event = Event::factory()->create([
        'organization_id' => $instance->organization_id,
        'status' => EventStatus::Active->value,
        'default_whatsapp_instance_id' => $instance->id,
        'whatsapp_instance_mode' => 'shared',
        'current_entitlements_json' => makeAutomationEntitlements(),
    ]);

    enableAutomationEventModule($event);

    EventChannel::query()->create([
        'event_id' => $event->id,
        'channel_type' => ChannelType::WhatsAppGroup->value,
        'provider' => 'zapi',
        'external_id' => 'GRUPOAUTO',
        'label' => 'WhatsApp grupos',
        'status' => 'active',
        'config_json' => [
            'group_bind_code' => 'GRUPOAUTO',
            'groups' => [
                [
                    'group_external_id' => '120363499999999999-group',
                    'group_name' => 'Grupo Fixture Evento',
                    'is_active' => true,
                    'auto_feedback_enabled' => true,
                ],
            ],
        ],
    ]);

    WhatsAppGroupBinding::query()->create([
        'organization_id' => $event->organization_id,
        'event_id' => $event->id,
        'instance_id' => $instance->id,
        'group_external_id' => '120363499999999999-group',
        'group_name' => 'Grupo Fixture Evento',
        'binding_type' => GroupBindingType::EventGallery->value,
        'is_active' => true,
        'metadata_json' => [],
    ]);

    $response = $this
        ->withHeaders(['X-Trace-Id' => 'trace-whatsapp-inbound-001'])
        ->postJson(
            "/api/v1/webhooks/whatsapp/zapi/{$instance->external_instance_id}/inbound",
            loadZApiAutomationFixture('group-image-with-caption'),
        );

    $response->assertOk()
        ->assertHeader('X-Trace-Id', 'trace-whatsapp-inbound-001')
        ->assertJson(['status' => 'received']);

    expect(WhatsAppInboundEvent::query()->latest('id')->value('trace_id'))->toBe('trace-whatsapp-inbound-001');

    Bus::assertDispatched(ProcessInboundMediaWebhookJob::class, function (ProcessInboundMediaWebhookJob $job) {
        return data_get($job->payload, '_event_context.trace_id') === 'trace-whatsapp-inbound-001';
    });
});

it('sends a single published feedback reaction when the media becomes published', function () {
    Bus::fake([SendWhatsAppMessageJob::class]);

    $instance = WhatsAppInstance::factory()->connected()->create([
        'external_instance_id' => 'INSTANCE-FIXTURE-001',
    ]);

    $event = Event::factory()->create([
        'organization_id' => $instance->organization_id,
        'status' => EventStatus::Active->value,
        'default_whatsapp_instance_id' => $instance->id,
        'whatsapp_instance_mode' => 'shared',
        'current_entitlements_json' => makeAutomationEntitlements(),
    ]);

    enableAutomationEventModule($event);

    $channel = EventChannel::query()->create([
        'event_id' => $event->id,
        'channel_type' => ChannelType::WhatsAppGroup->value,
        'provider' => 'zapi',
        'external_id' => 'GRUPOAUTO',
        'label' => 'WhatsApp grupos',
        'status' => 'active',
        'config_json' => [
            'group_bind_code' => 'GRUPOAUTO',
            'groups' => [
                [
                    'group_external_id' => '120363499999999999-group',
                    'group_name' => 'Grupo Fixture Evento',
                    'is_active' => true,
                    'auto_feedback_enabled' => true,
                ],
            ],
        ],
    ]);

    [$inboundMessage, $eventMedia] = createInboundMediaWithEventContext($event, $channel);

    app(SendFeedbackOnMediaPublished::class)->handle(MediaPublished::fromMedia($eventMedia));
    app(SendFeedbackOnMediaPublished::class)->handle(MediaPublished::fromMedia($eventMedia));

    $reaction = WhatsAppMessage::query()
        ->where('direction', MessageDirection::Outbound)
        ->where('type', 'reaction')
        ->sole();

    $feedback = WhatsAppMessageFeedback::query()->sole();

    expect($reaction->recipient_phone)->toBe('120363499999999999-group')
        ->and($reaction->reply_to_provider_message_id)->toBe('2A20028071DA23E04188')
        ->and(data_get($reaction->payload_json, 'messageId'))->toBe('2A20028071DA23E04188')
        ->and($feedback->event_id)->toBe($event->id)
        ->and($feedback->event_media_id)->toBe($eventMedia->id)
        ->and($feedback->inbound_message_id)->toBe($inboundMessage->id)
        ->and($feedback->feedback_kind)->toBe('reaction')
        ->and($feedback->feedback_phase)->toBe('published')
        ->and($feedback->status)->toBe('sent');
});

it('sends rejected feedback after publication without colliding with the published heart phase', function () {
    Bus::fake([SendWhatsAppMessageJob::class]);

    $instance = WhatsAppInstance::factory()->connected()->create([
        'external_instance_id' => 'INSTANCE-FIXTURE-001',
    ]);

    $event = Event::factory()->create([
        'organization_id' => $instance->organization_id,
        'status' => EventStatus::Active->value,
        'default_whatsapp_instance_id' => $instance->id,
        'whatsapp_instance_mode' => 'shared',
        'current_entitlements_json' => makeAutomationEntitlements(),
    ]);

    enableAutomationEventModule($event);

    $channel = EventChannel::query()->create([
        'event_id' => $event->id,
        'channel_type' => ChannelType::WhatsAppGroup->value,
        'provider' => 'zapi',
        'external_id' => 'GRUPOAUTO',
        'label' => 'WhatsApp grupos',
        'status' => 'active',
        'config_json' => [
            'group_bind_code' => 'GRUPOAUTO',
            'groups' => [
                [
                    'group_external_id' => '120363499999999999-group',
                    'group_name' => 'Grupo Fixture Evento',
                    'is_active' => true,
                    'auto_feedback_enabled' => true,
                ],
            ],
        ],
    ]);

    [$inboundMessage, $eventMedia] = createInboundMediaWithEventContext($event, $channel);

    app(SendFeedbackOnMediaPublished::class)->handle(MediaPublished::fromMedia($eventMedia));
    app(SendFeedbackOnMediaRejected::class)->handle(MediaRejected::fromMedia($eventMedia));

    $outbound = WhatsAppMessage::query()
        ->where('direction', MessageDirection::Outbound)
        ->orderBy('id')
        ->get();

    $feedback = WhatsAppMessageFeedback::query()
        ->orderBy('id')
        ->get();

    expect($outbound)->toHaveCount(3)
        ->and($outbound[0]->type->value)->toBe('reaction')
        ->and($outbound[0]->reply_to_provider_message_id)->toBe($inboundMessage->message_id)
        ->and($outbound[1]->type->value)->toBe('reaction')
        ->and($outbound[1]->reply_to_provider_message_id)->toBe($inboundMessage->message_id)
        ->and($outbound[2]->type->value)->toBe('text')
        ->and($outbound[2]->reply_to_provider_message_id)->toBe($inboundMessage->message_id)
        ->and($outbound[2]->text_body)->toContain('diretrizes do evento');

    expect($feedback)->toHaveCount(3)
        ->and($feedback->where('feedback_phase', 'published')->count())->toBe(1)
        ->and($feedback->where('feedback_phase', 'published')->first()?->feedback_kind)->toBe('reaction')
        ->and($feedback->where('feedback_phase', 'rejected')->count())->toBe(2)
        ->and($feedback->where('feedback_phase', 'rejected')->pluck('feedback_kind')->all())->toBe(['reaction', 'reply'])
        ->and($feedback->where('feedback_phase', 'published')->first()?->event_media_id)->toBe($eventMedia->id)
        ->and($feedback->where('feedback_phase', 'rejected')->first()?->event_media_id)->toBe($eventMedia->id);
});

it('sends only the ai published reply after vlm completes for media already published', function () {
    Bus::fake([SendWhatsAppMessageJob::class]);

    app()->bind(VisualReasoningProviderInterface::class, fn () => new class implements VisualReasoningProviderInterface
    {
        public function evaluate(EventMedia $media, EventMediaIntelligenceSetting $settings): VisualReasoningEvaluationResult
        {
            return VisualReasoningEvaluationResult::approve(
                reason: 'Imagem compativel com o evento.',
                shortCaption: 'Legenda sugerida pela IA.',
                replyText: 'Momento de risadas e lembrancas! 📱🎉',
                tags: ['festa'],
                rawResponse: ['provider' => 'fake-vlm'],
                providerKey: 'fake-vlm',
                providerVersion: 'test-v1',
                modelKey: 'fake-vlm-model',
                modelSnapshot: 'fake-vlm-model@1',
                promptVersion: $settings->prompt_version,
                responseSchemaVersion: $settings->response_schema_version,
                modeApplied: $settings->mode,
                tokensInput: 77,
                tokensOutput: 18,
            );
        }
    });

    $instance = WhatsAppInstance::factory()->connected()->create([
        'external_instance_id' => 'INSTANCE-FIXTURE-001',
    ]);

    $event = Event::factory()->create([
        'organization_id' => $instance->organization_id,
        'status' => EventStatus::Active->value,
        'default_whatsapp_instance_id' => $instance->id,
        'whatsapp_instance_mode' => 'shared',
        'moderation_mode' => 'manual',
        'current_entitlements_json' => makeAutomationEntitlements(),
    ]);

    enableAutomationEventModule($event);

    EventMediaIntelligenceSetting::factory()->create([
        'event_id' => $event->id,
        'enabled' => true,
        'mode' => 'enrich_only',
        'reply_text_enabled' => true,
    ]);

    $channel = EventChannel::query()->create([
        'event_id' => $event->id,
        'channel_type' => ChannelType::WhatsAppGroup->value,
        'provider' => 'zapi',
        'external_id' => 'GRUPOAUTO',
        'label' => 'WhatsApp grupos',
        'status' => 'active',
        'config_json' => [
            'group_bind_code' => 'GRUPOAUTO',
            'groups' => [
                [
                    'group_external_id' => '120363499999999999-group',
                    'group_name' => 'Grupo Fixture Evento',
                    'is_active' => true,
                    'auto_feedback_enabled' => true,
                ],
            ],
        ],
    ]);

    [$inboundMessage, $eventMedia] = createInboundMediaWithEventContext($event, $channel);

    $eventMedia->forceFill([
        'publication_status' => PublicationStatus::Published->value,
        'moderation_status' => ModerationStatus::Approved->value,
        'vlm_status' => 'queued',
    ])->save();

    EventMediaVariant::query()->create([
        'event_media_id' => $eventMedia->id,
        'variant_key' => 'fast_preview',
        'disk' => 'public',
        'path' => "events/{$event->id}/variants/{$eventMedia->id}/fast_preview.webp",
        'mime_type' => 'image/webp',
        'width' => 512,
        'height' => 512,
        'size_bytes' => 2048,
    ]);

    app(SendFeedbackOnMediaPublished::class)->handle(MediaPublished::fromMedia($eventMedia));
    app(EvaluateMediaPromptJob::class, ['eventMediaId' => $eventMedia->id])->handle();

    $feedback = WhatsAppMessageFeedback::query()->orderBy('id')->get();
    $outbound = WhatsAppMessage::query()->where('direction', MessageDirection::Outbound)->orderBy('id')->get();
    $publishedReplyFeedback = $feedback->where('feedback_kind', 'reply')->first();

    /* expect($publishedReplyFeedback?->resolution_json)->toMatchArray([
        'mode' => 'ai',
        'source' => 'vlm',
        'reply_text' => 'Momento de risadas e lembrancas! ðŸ“±ðŸŽ‰',
    ]); */

    expect(data_get($publishedReplyFeedback?->resolution_json, 'mode'))->toBe('ai')
        ->and(data_get($publishedReplyFeedback?->resolution_json, 'source'))->toBe('vlm')
        ->and(data_get($publishedReplyFeedback?->resolution_json, 'reply_text'))->toBe($publishedReplyFeedback?->reply_text)
        ->and($publishedReplyFeedback?->trace_id)->toBe('trace-whatsapp-feedback-001');

    expect($feedback)->toHaveCount(2)
        ->and($feedback->where('feedback_phase', 'published')->pluck('feedback_kind')->all())->toBe(['reaction', 'reply'])
        ->and($feedback->where('feedback_kind', 'reply')->first()?->reply_text)->toBe('Momento de risadas e lembrancas! 📱🎉');

    expect($outbound)->toHaveCount(2)
        ->and($outbound[0]->type->value)->toBe('reaction')
        ->and($outbound[1]->type->value)->toBe('text')
        ->and($outbound[1]->text_body)->toBe('Momento de risadas e lembrancas! 📱🎉');
});

it('sends only the published reaction for video media when automatic reply mode is ai', function () {
    Bus::fake([SendWhatsAppMessageJob::class]);

    $instance = WhatsAppInstance::factory()->connected()->create([
        'external_instance_id' => 'INSTANCE-FIXTURE-001',
    ]);

    $event = Event::factory()->create([
        'organization_id' => $instance->organization_id,
        'status' => EventStatus::Active->value,
        'default_whatsapp_instance_id' => $instance->id,
        'whatsapp_instance_mode' => 'shared',
        'moderation_mode' => 'ai',
        'current_entitlements_json' => makeAutomationEntitlements(),
    ]);

    enableAutomationEventModule($event);

    EventMediaIntelligenceSetting::factory()->create([
        'event_id' => $event->id,
        'enabled' => true,
        'mode' => 'enrich_only',
        'reply_text_mode' => 'ai',
        'reply_text_enabled' => true,
    ]);

    $channel = EventChannel::query()->create([
        'event_id' => $event->id,
        'channel_type' => ChannelType::WhatsAppGroup->value,
        'provider' => 'zapi',
        'external_id' => 'GRUPOAUTO',
        'label' => 'WhatsApp grupos',
        'status' => 'active',
        'config_json' => [
            'group_bind_code' => 'GRUPOAUTO',
            'groups' => [
                [
                    'group_external_id' => '120363499999999999-group',
                    'group_name' => 'Grupo Fixture Evento',
                    'is_active' => true,
                    'auto_feedback_enabled' => true,
                ],
            ],
        ],
    ]);

    [$inboundMessage, $eventMedia] = createInboundMediaWithEventContext($event, $channel);

    $eventMedia->forceFill([
        'media_type' => 'video',
        'mime_type' => 'video/mp4',
        'original_filename' => 'entrada.mp4',
        'client_filename' => 'entrada.mp4',
        'original_path' => "events/{$event->id}/originals/entrada.mp4",
        'publication_status' => PublicationStatus::Published->value,
        'moderation_status' => ModerationStatus::Approved->value,
        'safety_status' => 'skipped',
        'vlm_status' => 'skipped',
    ])->save();

    app(SendFeedbackOnMediaPublished::class)->handle(MediaPublished::fromMedia($eventMedia));

    $feedback = WhatsAppMessageFeedback::query()->orderBy('id')->get();
    $outbound = WhatsAppMessage::query()->where('direction', MessageDirection::Outbound)->orderBy('id')->get();

    expect($feedback)->toHaveCount(1)
        ->and($feedback[0]->feedback_kind)->toBe('reaction')
        ->and($feedback[0]->feedback_phase)->toBe('published')
        ->and($feedback[0]->event_media_id)->toBe($eventMedia->id)
        ->and($feedback[0]->reply_text)->toBeNull();

    expect($outbound)->toHaveCount(1)
        ->and($outbound[0]->type->value)->toBe('reaction')
        ->and($outbound[0]->reply_to_provider_message_id)->toBe($inboundMessage->message_id);
});

it('sends a single rejected feedback bundle with reaction plus threaded reply when the media is rejected', function () {
    Bus::fake([SendWhatsAppMessageJob::class]);

    $instance = WhatsAppInstance::factory()->connected()->create([
        'external_instance_id' => 'INSTANCE-FIXTURE-001',
    ]);

    $event = Event::factory()->create([
        'organization_id' => $instance->organization_id,
        'status' => EventStatus::Active->value,
        'default_whatsapp_instance_id' => $instance->id,
        'whatsapp_instance_mode' => 'shared',
        'current_entitlements_json' => makeAutomationEntitlements(),
    ]);

    enableAutomationEventModule($event);

    $channel = EventChannel::query()->create([
        'event_id' => $event->id,
        'channel_type' => ChannelType::WhatsAppGroup->value,
        'provider' => 'zapi',
        'external_id' => 'GRUPOAUTO',
        'label' => 'WhatsApp grupos',
        'status' => 'active',
        'config_json' => [
            'group_bind_code' => 'GRUPOAUTO',
            'groups' => [
                [
                    'group_external_id' => '120363499999999999-group',
                    'group_name' => 'Grupo Fixture Evento',
                    'is_active' => true,
                    'auto_feedback_enabled' => true,
                ],
            ],
        ],
    ]);

    [$inboundMessage, $eventMedia] = createInboundMediaWithEventContext($event, $channel);

    app(SendFeedbackOnMediaRejected::class)->handle(MediaRejected::fromMedia($eventMedia));
    app(SendFeedbackOnMediaRejected::class)->handle(MediaRejected::fromMedia($eventMedia));

    $outbound = WhatsAppMessage::query()
        ->where('direction', MessageDirection::Outbound)
        ->orderBy('id')
        ->get();

    $feedback = WhatsAppMessageFeedback::query()
        ->orderBy('id')
        ->get();

    expect($outbound)->toHaveCount(2)
        ->and($outbound[0]->type->value)->toBe('reaction')
        ->and($outbound[0]->recipient_phone)->toBe('120363499999999999-group')
        ->and($outbound[0]->reply_to_provider_message_id)->toBe('2A20028071DA23E04188')
        ->and($outbound[1]->type->value)->toBe('text')
        ->and($outbound[1]->recipient_phone)->toBe('120363499999999999-group')
        ->and($outbound[1]->reply_to_provider_message_id)->toBe('2A20028071DA23E04188')
        ->and(data_get($outbound[1]->payload_json, 'privateAnswer'))->toBeFalse()
        ->and($outbound[1]->text_body)->toContain('diretrizes do evento');

    expect($feedback)->toHaveCount(2)
        ->and($feedback[0]->feedback_kind)->toBe('reaction')
        ->and($feedback[0]->feedback_phase)->toBe('rejected')
        ->and($feedback[0]->status)->toBe('sent')
        ->and($feedback[1]->feedback_kind)->toBe('reply')
        ->and($feedback[1]->feedback_phase)->toBe('rejected')
        ->and($feedback[1]->status)->toBe('sent')
        ->and($feedback[1]->event_id)->toBe($event->id)
        ->and($feedback[1]->event_media_id)->toBe($eventMedia->id)
        ->and($feedback[1]->inbound_message_id)->toBe($inboundMessage->id);
});

it('sends rejected whatsapp feedback end-to-end when a manager rejects media manually', function () {
    Bus::fake([SendWhatsAppMessageJob::class]);

    [$user, $organization] = $this->actingAsOwner();

    $instance = WhatsAppInstance::factory()->connected()->create([
        'organization_id' => $organization->id,
        'external_instance_id' => 'INSTANCE-FIXTURE-001',
    ]);

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'status' => EventStatus::Active->value,
        'moderation_mode' => 'manual',
        'default_whatsapp_instance_id' => $instance->id,
        'whatsapp_instance_mode' => 'shared',
        'current_entitlements_json' => makeAutomationEntitlements(),
    ]);

    enableAutomationEventModule($event);

    $channel = EventChannel::query()->create([
        'event_id' => $event->id,
        'channel_type' => ChannelType::WhatsAppGroup->value,
        'provider' => 'zapi',
        'external_id' => 'GRUPOAUTO',
        'label' => 'WhatsApp grupos',
        'status' => 'active',
        'config_json' => [
            'group_bind_code' => 'GRUPOAUTO',
            'groups' => [
                [
                    'group_external_id' => '120363499999999999-group',
                    'group_name' => 'Grupo Fixture Evento',
                    'is_active' => true,
                    'auto_feedback_enabled' => true,
                ],
            ],
        ],
    ]);

    [$inboundMessage, $eventMedia] = createInboundMediaWithEventContext($event, $channel);

    $response = $this->apiPost("/media/{$eventMedia->id}/reject", [
        'reason' => 'Nao atende as diretrizes do evento',
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.decision_source', 'user_override')
        ->assertJsonPath('data.publication_status', 'draft');

    $outbound = WhatsAppMessage::query()
        ->where('direction', MessageDirection::Outbound)
        ->orderBy('id')
        ->get();

    $feedback = WhatsAppMessageFeedback::query()
        ->orderBy('id')
        ->get();

    expect($eventMedia->fresh()->moderation_status)->toBe(ModerationStatus::Rejected)
        ->and($outbound)->toHaveCount(2)
        ->and($outbound[0]->type->value)->toBe('reaction')
        ->and($outbound[1]->type->value)->toBe('text')
        ->and($outbound[1]->reply_to_provider_message_id)->toBe($inboundMessage->message_id)
        ->and($feedback)->toHaveCount(2)
        ->and($feedback[0]->feedback_phase)->toBe('rejected')
        ->and($feedback[1]->feedback_phase)->toBe('rejected');
});

it('sends rejected whatsapp feedback end-to-end when ai moderation blocks the media', function () {
    Bus::fake([SendWhatsAppMessageJob::class]);

    $instance = WhatsAppInstance::factory()->connected()->create([
        'external_instance_id' => 'INSTANCE-FIXTURE-001',
    ]);

    $event = Event::factory()->create([
        'organization_id' => $instance->organization_id,
        'status' => EventStatus::Active->value,
        'moderation_mode' => 'ai',
        'default_whatsapp_instance_id' => $instance->id,
        'whatsapp_instance_mode' => 'shared',
        'current_entitlements_json' => makeAutomationEntitlements(),
    ]);

    enableAutomationEventModule($event);

    $channel = EventChannel::query()->create([
        'event_id' => $event->id,
        'channel_type' => ChannelType::WhatsAppGroup->value,
        'provider' => 'zapi',
        'external_id' => 'GRUPOAUTO',
        'label' => 'WhatsApp grupos',
        'status' => 'active',
        'config_json' => [
            'group_bind_code' => 'GRUPOAUTO',
            'groups' => [
                [
                    'group_external_id' => '120363499999999999-group',
                    'group_name' => 'Grupo Fixture Evento',
                    'is_active' => true,
                    'auto_feedback_enabled' => true,
                ],
            ],
        ],
    ]);

    [$inboundMessage, $eventMedia] = createInboundMediaWithEventContext($event, $channel);

    $eventMedia->forceFill([
        'moderation_status' => ModerationStatus::Pending,
        'publication_status' => PublicationStatus::Draft,
        'safety_status' => 'block',
        'vlm_status' => 'skipped',
    ])->save();

    app(RunModerationJob::class, ['eventMediaId' => $eventMedia->id])->handle();

    $outbound = WhatsAppMessage::query()
        ->where('direction', MessageDirection::Outbound)
        ->orderBy('id')
        ->get();

    $feedback = WhatsAppMessageFeedback::query()
        ->orderBy('id')
        ->get();

    expect($eventMedia->fresh()->moderation_status)->toBe(ModerationStatus::Rejected)
        ->and($outbound)->toHaveCount(2)
        ->and($outbound[0]->type->value)->toBe('reaction')
        ->and($outbound[1]->type->value)->toBe('text')
        ->and($outbound[0]->reply_to_provider_message_id)->toBe($inboundMessage->message_id)
        ->and($feedback)->toHaveCount(2)
        ->and($feedback[0]->feedback_phase)->toBe('rejected')
        ->and($feedback[1]->feedback_phase)->toBe('rejected');
});
