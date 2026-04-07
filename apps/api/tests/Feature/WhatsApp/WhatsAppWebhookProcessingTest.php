<?php

use App\Modules\WhatsApp\Enums\ChatType;
use App\Modules\WhatsApp\Enums\InstanceStatus;
use App\Modules\WhatsApp\Enums\InboundEventStatus;
use App\Modules\WhatsApp\Enums\MessageDirection;
use App\Modules\WhatsApp\Enums\MessageStatus;
use App\Modules\WhatsApp\Enums\MessageType;
use App\Modules\WhatsApp\Events\WhatsAppInstanceStatusChanged;
use App\Modules\WhatsApp\Models\WhatsAppInboundEvent;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Event;

function makeReceivedCallbackPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'type' => 'ReceivedCallback',
        'instanceId' => 'INSTANCE-001',
        'messageId' => '3EB0B1E03BB6FAEACB6BC8',
        'momment' => '2026-04-05T12:37:42-03:00',
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
            'imageUrl' => 'https://cdn.z-api.io/media/image-1.jpg',
            'caption' => 'teste com legenda',
            'mimeType' => 'image/jpeg',
        ],
    ], $overrides);
}

function makeMessageStatusCallbackPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'type' => 'MessageStatusCallback',
        'instanceId' => 'INSTANCE-001',
        'momment' => '2026-04-05T13:04:29-03:00',
        'phone' => '5548998483594',
        'status' => 'READ',
        'ids' => ['4A196C7DE3AE65FE6A66'],
        'isGroup' => false,
    ], $overrides);
}

function makeConnectedCallbackPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'type' => 'ConnectedCallback',
        'instanceId' => 'INSTANCE-001',
        'momment' => '2026-04-05T13:10:00-03:00',
        'connected' => true,
        'phone' => '5548998483594',
    ], $overrides);
}

function makeDisconnectedCallbackPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'type' => 'DisconnectedCallback',
        'instanceId' => 'INSTANCE-001',
        'momment' => '2026-04-05T13:15:00-03:00',
        'disconnected' => true,
        'phone' => '5548998483594',
        'error' => 'Connection closed by remote peer',
    ], $overrides);
}

it('processes zapi received callbacks as inbound messages with participant identity and media caption', function () {
    $instance = WhatsAppInstance::factory()->create([
        'external_instance_id' => 'INSTANCE-001',
    ]);

    $response = $this->postJson(
        "/api/v1/webhooks/whatsapp/zapi/{$instance->external_instance_id}/inbound",
        makeReceivedCallbackPayload(),
    );

    $response
        ->assertOk()
        ->assertJson(['status' => 'received']);

    $event = WhatsAppInboundEvent::query()->sole();
    $message = WhatsAppMessage::query()->sole();
    $chat = $message->chat()->firstOrFail();

    expect($event->event_type)->toBe('message')
        ->and($event->processing_status)->toBe(InboundEventStatus::Processed)
        ->and(data_get($event->normalized_json, 'callback_type'))->toBe('ReceivedCallback')
        ->and(data_get($event->normalized_json, 'sender_phone'))->toBe('554896553954')
        ->and(data_get($event->normalized_json, 'participant_phone'))->toBe('554896553954')
        ->and(data_get($event->normalized_json, 'chat_name'))->toBe('Evento vivo 1')
        ->and(data_get($event->normalized_json, 'from_me'))->toBeFalse();

    expect($message->direction)->toBe(MessageDirection::Inbound)
        ->and($message->provider_message_id)->toBe('3EB0B1E03BB6FAEACB6BC8')
        ->and($message->type)->toBe(MessageType::Image)
        ->and($message->status)->toBe(MessageStatus::Received)
        ->and($message->sender_phone)->toBe('554896553954')
        ->and($message->text_body)->toBe('teste com legenda')
        ->and($message->media_url)->toBe('https://cdn.z-api.io/media/image-1.jpg')
        ->and($message->received_at?->toIso8601String())->toBe('2026-04-05T15:37:42+00:00');

    expect($chat->type)->toBe(ChatType::Group)
        ->and($chat->external_chat_id)->toBe('120363425796926861-group')
        ->and($chat->group_id)->toBe('120363425796926861-group')
        ->and($chat->display_name)->toBe('Evento vivo 1')
        ->and($chat->phone)->toBeNull()
        ->and($chat->is_group)->toBeTrue();
});

it('normalizes zapi numeric millisecond timestamps on received callbacks', function () {
    $instance = WhatsAppInstance::factory()->create([
        'external_instance_id' => 'INSTANCE-001',
    ]);

    $this->postJson(
        "/api/v1/webhooks/whatsapp/zapi/{$instance->external_instance_id}/inbound",
        makeReceivedCallbackPayload([
            'messageId' => '3EB0NUMERICMS0001',
            'momment' => 1775392658000,
        ]),
    )->assertOk();

    $message = WhatsAppMessage::query()
        ->where('provider_message_id', '3EB0NUMERICMS0001')
        ->sole();

    expect($message->received_at?->toIso8601String())->toBe('2026-04-05T12:37:38+00:00');
});

it('updates outbound message status from zapi message status callbacks without creating inbound messages', function () {
    $instance = WhatsAppInstance::factory()->create([
        'external_instance_id' => 'INSTANCE-001',
    ]);

    $chat = $instance->chats()->create([
        'external_chat_id' => '5548998483594',
        'type' => ChatType::Private,
        'phone' => '5548998483594',
        'display_name' => 'Cliente Teste',
        'is_group' => false,
    ]);

    $message = WhatsAppMessage::create([
        'instance_id' => $instance->id,
        'chat_id' => $chat->id,
        'direction' => MessageDirection::Outbound,
        'provider_message_id' => '4A196C7DE3AE65FE6A66',
        'type' => MessageType::Text,
        'text_body' => 'Mensagem enviada',
        'status' => MessageStatus::Sent,
        'recipient_phone' => '5548998483594',
        'payload_json' => ['messageId' => '4A196C7DE3AE65FE6A66'],
        'sent_at' => now()->subMinute(),
    ]);

    $response = $this->postJson(
        "/api/v1/webhooks/whatsapp/zapi/{$instance->external_instance_id}/delivery",
        makeMessageStatusCallbackPayload(),
    );

    $response
        ->assertOk()
        ->assertJson(['status' => 'received']);

    $event = WhatsAppInboundEvent::query()->sole();

    expect(WhatsAppMessage::count())->toBe(1)
        ->and($message->refresh()->status)->toBe(MessageStatus::Read)
        ->and($event->event_type)->toBe('delivery')
        ->and($event->processing_status)->toBe(InboundEventStatus::Processed)
        ->and(data_get($event->normalized_json, 'callback_type'))->toBe('MessageStatusCallback')
        ->and(data_get($event->normalized_json, 'message_status'))->toBe('READ')
        ->and(data_get($event->normalized_json, 'message_ids'))->toBe(['4A196C7DE3AE65FE6A66']);
});

it('normalizes zapi numeric microsecond timestamps on message status callbacks', function () {
    $instance = WhatsAppInstance::factory()->create([
        'external_instance_id' => 'INSTANCE-001',
    ]);

    $chat = $instance->chats()->create([
        'external_chat_id' => '120363424631089763-group',
        'type' => ChatType::Group,
        'group_id' => '120363424631089763-group',
        'display_name' => 'Grupo Status Real',
        'is_group' => true,
    ]);

    $message = WhatsAppMessage::create([
        'instance_id' => $instance->id,
        'chat_id' => $chat->id,
        'direction' => MessageDirection::Outbound,
        'provider_message_id' => '3AE862B1ED1EEC107BDC',
        'type' => MessageType::Text,
        'text_body' => 'Mensagem enviada',
        'status' => MessageStatus::Sent,
        'recipient_phone' => '120363424631089763-group',
        'payload_json' => ['messageId' => '3AE862B1ED1EEC107BDC'],
        'sent_at' => now()->subMinute(),
    ]);

    $this->postJson(
        "/api/v1/webhooks/whatsapp/zapi/{$instance->external_instance_id}/delivery",
        makeMessageStatusCallbackPayload([
            'phone' => '120363424631089763-group',
            'status' => 'READ_BY_ME',
            'ids' => ['3AE862B1ED1EEC107BDC'],
            'isGroup' => true,
            'momment' => 1775501632404000,
            'participant' => '554896553954',
            'participantDevice' => 39,
        ]),
    )->assertOk();

    $event = WhatsAppInboundEvent::query()
        ->where('provider_message_id', '3AE862B1ED1EEC107BDC')
        ->sole();

    expect($event->processing_status)->toBe(InboundEventStatus::Processed)
        ->and($event->error_message)->toBeNull()
        ->and(data_get($event->normalized_json, 'message_status'))->toBe('READ_BY_ME')
        ->and(data_get($event->normalized_json, 'occurred_at'))->toBe('2026-04-06T18:53:52+00:00')
        ->and($message->refresh()->status)->toBe(MessageStatus::Read);
});

it('updates the instance lifecycle from zapi connected and disconnected callbacks', function () {
    Event::fake([WhatsAppInstanceStatusChanged::class]);

    $instance = WhatsAppInstance::factory()->create([
        'external_instance_id' => 'INSTANCE-001',
        'status' => InstanceStatus::Configured,
        'phone_number' => null,
        'last_health_status' => null,
        'connected_at' => null,
        'disconnected_at' => null,
    ]);

    $connectedResponse = $this->postJson(
        "/api/v1/webhooks/whatsapp/zapi/{$instance->external_instance_id}/status",
        makeConnectedCallbackPayload(),
    );

    $connectedResponse
        ->assertOk()
        ->assertJson(['status' => 'received']);

    $instance->refresh();

    expect($instance->status)->toBe(InstanceStatus::Connected)
        ->and($instance->phone_number)->toBe('5548998483594')
        ->and($instance->last_health_status)->toBe('connected')
        ->and($instance->connected_at?->toIso8601String())->toBe('2026-04-05T16:10:00+00:00')
        ->and($instance->disconnected_at)->toBeNull();

    Event::assertDispatched(WhatsAppInstanceStatusChanged::class, function (WhatsAppInstanceStatusChanged $event) use ($instance) {
        return $event->instance->is($instance)
            && $event->previousStatus === InstanceStatus::Configured
            && $event->newStatus === InstanceStatus::Connected;
    });

    $disconnectedResponse = $this->postJson(
        "/api/v1/webhooks/whatsapp/zapi/{$instance->external_instance_id}/status",
        makeDisconnectedCallbackPayload(),
    );

    $disconnectedResponse
        ->assertOk()
        ->assertJson(['status' => 'received']);

    $instance->refresh();

    expect(WhatsAppMessage::count())->toBe(0)
        ->and($instance->status)->toBe(InstanceStatus::Disconnected)
        ->and($instance->last_health_status)->toBe('disconnected')
        ->and($instance->last_error)->toBe('Connection closed by remote peer')
        ->and($instance->disconnected_at?->toIso8601String())->toBe('2026-04-05T16:15:00+00:00');

    Event::assertDispatched(WhatsAppInstanceStatusChanged::class, function (WhatsAppInstanceStatusChanged $event) use ($instance) {
        return $event->instance->is($instance)
            && $event->previousStatus === InstanceStatus::Connected
            && $event->newStatus === InstanceStatus::Disconnected;
    });

    expect(WhatsAppInboundEvent::count())->toBe(2);
});
