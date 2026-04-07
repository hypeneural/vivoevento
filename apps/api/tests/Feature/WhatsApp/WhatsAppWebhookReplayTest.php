<?php

use App\Modules\WhatsApp\Enums\InboundEventStatus;
use App\Modules\WhatsApp\Enums\MessageDirection;
use App\Modules\WhatsApp\Enums\MessageStatus;
use App\Modules\WhatsApp\Models\WhatsAppInboundEvent;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use App\Modules\WhatsApp\Models\WhatsAppMessage;

function loadZApiReplayFixture(string $name, array $overrides = []): array
{
    $payload = json_decode(
        file_get_contents(base_path("tests/Fixtures/WhatsApp/ZApi/{$name}.json")),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    return array_replace_recursive($payload, $overrides);
}

it('processes a real anonymized group image fixture preserving participant identity and caption', function () {
    $instance = WhatsAppInstance::factory()->create([
        'external_instance_id' => 'INSTANCE-FIXTURE-001',
    ]);

    $response = $this->postJson(
        "/api/v1/webhooks/whatsapp/zapi/{$instance->external_instance_id}/inbound",
        loadZApiReplayFixture('group-image-with-caption'),
    );

    $response->assertOk()->assertJson(['status' => 'received']);

    $event = WhatsAppInboundEvent::query()->sole();
    $message = WhatsAppMessage::query()->sole();

    expect($event->event_type)->toBe('message')
        ->and($event->processing_status)->toBe(InboundEventStatus::Processed)
        ->and(data_get($event->normalized_json, 'participant_phone'))->toBe('554899991111')
        ->and(data_get($event->normalized_json, 'participant_lid'))->toBe('11111111111111@lid')
        ->and(data_get($event->normalized_json, 'chat_name'))->toBe('Grupo Fixture Evento');

    expect($message->direction)->toBe(MessageDirection::Inbound)
        ->and($message->type->value)->toBe('image')
        ->and($message->status)->toBe(MessageStatus::Received)
        ->and($message->sender_phone)->toBe('554899991111')
        ->and($message->text_body)->toBe('Teste de grupo')
        ->and($message->media_url)->toBe('https://cdn.fixture.test/zapi/group-image-with-caption.jpg');
});

it('ignores a real anonymized group notification fixture without creating inbound messages', function () {
    $instance = WhatsAppInstance::factory()->create([
        'external_instance_id' => 'INSTANCE-FIXTURE-001',
    ]);

    $response = $this->postJson(
        "/api/v1/webhooks/whatsapp/zapi/{$instance->external_instance_id}/inbound",
        loadZApiReplayFixture('group-notification'),
    );

    $response->assertOk()->assertJson(['status' => 'received']);

    $event = WhatsAppInboundEvent::query()->sole();

    expect($event->event_type)->toBe('message')
        ->and($event->processing_status)->toBe(InboundEventStatus::Ignored)
        ->and(WhatsAppMessage::count())->toBe(0);
});

it('ignores a real anonymized reaction fixture without creating inbound messages', function () {
    $instance = WhatsAppInstance::factory()->create([
        'external_instance_id' => 'INSTANCE-FIXTURE-001',
    ]);

    $response = $this->postJson(
        "/api/v1/webhooks/whatsapp/zapi/{$instance->external_instance_id}/inbound",
        loadZApiReplayFixture('reaction'),
    );

    $response->assertOk()->assertJson(['status' => 'received']);

    $event = WhatsAppInboundEvent::query()->sole();

    expect($event->event_type)->toBe('message')
        ->and($event->processing_status)->toBe(InboundEventStatus::Ignored)
        ->and(data_get($event->normalized_json, 'message_type'))->toBe('reaction')
        ->and(WhatsAppMessage::count())->toBe(0);
});

it('updates outbound message status from a real anonymized message status fixture without creating inbound messages', function () {
    $instance = WhatsAppInstance::factory()->create([
        'external_instance_id' => 'INSTANCE-FIXTURE-001',
    ]);

    $chat = $instance->chats()->create([
        'external_chat_id' => '554899994444',
        'type' => 'private',
        'phone' => '554899994444',
        'display_name' => 'Contato DM Fixture',
        'is_group' => false,
    ]);

    $message = WhatsAppMessage::query()->create([
        'instance_id' => $instance->id,
        'chat_id' => $chat->id,
        'direction' => MessageDirection::Outbound,
        'provider_message_id' => '2A13B9BC2F436E87513E',
        'type' => 'text',
        'text_body' => 'Mensagem outbound',
        'status' => MessageStatus::Sent,
        'recipient_phone' => '554899994444',
        'payload_json' => ['messageId' => '2A13B9BC2F436E87513E'],
        'sent_at' => now()->subMinute(),
    ]);

    $response = $this->postJson(
        "/api/v1/webhooks/whatsapp/zapi/{$instance->external_instance_id}/delivery",
        loadZApiReplayFixture('message-status'),
    );

    $response->assertOk()->assertJson(['status' => 'received']);

    $event = WhatsAppInboundEvent::query()->sole();

    expect(WhatsAppMessage::count())->toBe(1)
        ->and($message->refresh()->status)->toBe(MessageStatus::Read)
        ->and($event->processing_status)->toBe(InboundEventStatus::Processed)
        ->and(data_get($event->normalized_json, 'message_ids'))->toBe([
            '2A13B9BC2F436E87513E',
            '2AEE16D9CE23E523951E',
        ]);
});
