<?php

use App\Modules\WhatsApp\Clients\DTOs\NormalizedInboundMessageData;
use App\Modules\WhatsApp\Enums\ChatType;
use App\Modules\WhatsApp\Enums\MessageDirection;
use App\Modules\WhatsApp\Enums\MessageStatus;
use App\Modules\WhatsApp\Enums\MessageType;
use App\Modules\WhatsApp\Events\WhatsAppMessageReceived;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Services\WhatsAppInboundRouter;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;

function makeNormalizedInboundMessage(array $overrides = []): NormalizedInboundMessageData
{
    return new NormalizedInboundMessageData(
        providerKey: $overrides['providerKey'] ?? 'zapi',
        instanceExternalId: $overrides['instanceExternalId'] ?? 'INSTANCE-001',
        eventType: $overrides['eventType'] ?? 'message',
        messageId: $overrides['messageId'] ?? 'wamid.MSG-001',
        chatId: $overrides['chatId'] ?? '5511999999999@c.us',
        chatType: $overrides['chatType'] ?? 'private',
        groupId: $overrides['groupId'] ?? null,
        senderPhone: $overrides['senderPhone'] ?? '5511999999999',
        senderName: $overrides['senderName'] ?? 'Cliente Teste',
        messageType: $overrides['messageType'] ?? MessageType::Text->value,
        text: $overrides['text'] ?? 'ola mundo',
        mediaUrl: $overrides['mediaUrl'] ?? null,
        mimeType: $overrides['mimeType'] ?? null,
        caption: $overrides['caption'] ?? null,
        occurredAt: $overrides['occurredAt'] ?? CarbonImmutable::parse('2026-04-04 10:00:00'),
        rawPayload: $overrides['rawPayload'] ?? ['messageId' => $overrides['messageId'] ?? 'wamid.MSG-001'],
    );
}

it('enforces inbound provider message uniqueness at database level per instance and direction', function () {
    $instance = WhatsAppInstance::factory()->create();
    $chat = $instance->chats()->create([
        'external_chat_id' => '5511999999999@c.us',
        'type' => ChatType::Private,
        'phone' => '5511999999999',
        'display_name' => 'Cliente Teste',
        'is_group' => false,
    ]);

    $payload = [
        'instance_id' => $instance->id,
        'chat_id' => $chat->id,
        'direction' => MessageDirection::Inbound,
        'provider_message_id' => 'wamid.MSG-001',
        'type' => MessageType::Text,
        'text_body' => 'primeira mensagem',
        'status' => MessageStatus::Received,
        'sender_phone' => '5511999999999',
        'normalized_payload_json' => ['messageId' => 'wamid.MSG-001'],
        'received_at' => now(),
    ];

    WhatsAppMessage::create($payload);

    expect(fn () => WhatsAppMessage::create($payload))
        ->toThrow(QueryException::class);
});

it('allows the same provider message id for different directions', function () {
    $instance = WhatsAppInstance::factory()->create();
    $chat = $instance->chats()->create([
        'external_chat_id' => '5511999999999@c.us',
        'type' => ChatType::Private,
        'phone' => '5511999999999',
        'display_name' => 'Cliente Teste',
        'is_group' => false,
    ]);

    WhatsAppMessage::create([
        'instance_id' => $instance->id,
        'chat_id' => $chat->id,
        'direction' => MessageDirection::Inbound,
        'provider_message_id' => 'wamid.MSG-001',
        'type' => MessageType::Text,
        'text_body' => 'mensagem inbound',
        'status' => MessageStatus::Received,
        'sender_phone' => '5511999999999',
        'normalized_payload_json' => ['messageId' => 'wamid.MSG-001'],
        'received_at' => now(),
    ]);

    WhatsAppMessage::create([
        'instance_id' => $instance->id,
        'chat_id' => $chat->id,
        'direction' => MessageDirection::Outbound,
        'provider_message_id' => 'wamid.MSG-001',
        'type' => MessageType::Text,
        'text_body' => 'mensagem outbound',
        'status' => MessageStatus::Sent,
        'recipient_phone' => '5511999999999',
        'payload_json' => ['messageId' => 'wamid.MSG-001'],
        'sent_at' => now(),
    ]);

    expect(WhatsAppMessage::count())->toBe(2);
});

it('returns the existing inbound message without dispatching the event twice', function () {
    Event::fake([WhatsAppMessageReceived::class]);

    $instance = WhatsAppInstance::factory()->create([
        'external_instance_id' => 'INSTANCE-001',
    ]);

    $router = app(WhatsAppInboundRouter::class);
    $normalized = makeNormalizedInboundMessage();

    $first = $router->route($normalized, $instance);
    $second = $router->route($normalized, $instance);

    expect($second->is($first))->toBeTrue();
    expect(WhatsAppMessage::count())->toBe(1);

    Event::assertDispatchedTimes(WhatsAppMessageReceived::class, 1);
});
