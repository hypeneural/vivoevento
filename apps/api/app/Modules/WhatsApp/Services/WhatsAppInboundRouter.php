<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\WhatsApp\Clients\DTOs\NormalizedInboundMessageData;
use App\Modules\WhatsApp\Enums\ChatType;
use App\Modules\WhatsApp\Enums\GroupBindingType;
use App\Modules\WhatsApp\Enums\MessageDirection;
use App\Modules\WhatsApp\Enums\MessageStatus;
use App\Modules\WhatsApp\Events\WhatsAppMessageReceived;
use App\Modules\WhatsApp\Models\WhatsAppChat;
use App\Modules\WhatsApp\Models\WhatsAppGroupBinding;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Log;

/**
 * Routes normalized inbound messages to the appropriate internal handlers.
 *
 * Responsibilities:
 * 1. Identify instance by external ID
 * 2. Find or create chat
 * 3. Create WhatsAppMessage (direction=inbound)
 * 4. Check group bindings → if event linked, dispatch media pipeline
 * 5. Check automation rules → auto-reaction, auto-reply
 */
class WhatsAppInboundRouter
{
    /**
     * Route a normalized inbound message.
     */
    public function route(NormalizedInboundMessageData $normalized, WhatsAppInstance $instance): WhatsAppMessage
    {
        // 1. Find or create chat
        $chat = $this->findOrCreateChat($instance, $normalized);

        // 2. Deduplication by provider_message_id
        $existing = WhatsAppMessage::where('instance_id', $instance->id)
            ->where('provider_message_id', $normalized->messageId)
            ->where('direction', MessageDirection::Inbound)
            ->first();

        if ($existing) {
            Log::channel('whatsapp')->info('Duplicate inbound message ignored', [
                'instance_id' => $instance->id,
                'message_id' => $normalized->messageId,
            ]);
            return $existing;
        }

        // 3. Create message record
        $message = WhatsAppMessage::create([
            'instance_id' => $instance->id,
            'chat_id' => $chat->id,
            'direction' => MessageDirection::Inbound,
            'provider_message_id' => $normalized->messageId,
            'type' => $normalized->messageType,
            'text_body' => $normalized->text,
            'media_url' => $normalized->mediaUrl,
            'mime_type' => $normalized->mimeType,
            'status' => MessageStatus::Received,
            'sender_phone' => $normalized->senderPhone,
            'normalized_payload_json' => $normalized->rawPayload,
            'received_at' => $normalized->occurredAt,
        ]);

        // 4. Update chat last_message_at
        $chat->update(['last_message_at' => $normalized->occurredAt]);

        // 5. Dispatch internal event for listeners
        WhatsAppMessageReceived::dispatch($message, $normalized, $this->findGroupBinding($instance, $normalized));

        Log::channel('whatsapp')->info('Inbound message routed', [
            'instance_id' => $instance->id,
            'message_id' => $message->id,
            'type' => $normalized->messageType,
            'is_group' => $normalized->isFromGroup(),
            'has_media' => $normalized->hasMedia(),
        ]);

        return $message;
    }

    /**
     * Find or create a chat for the inbound message.
     */
    private function findOrCreateChat(WhatsAppInstance $instance, NormalizedInboundMessageData $normalized): WhatsAppChat
    {
        return WhatsAppChat::firstOrCreate(
            [
                'instance_id' => $instance->id,
                'external_chat_id' => $normalized->chatId,
            ],
            [
                'type' => $normalized->isFromGroup() ? ChatType::Group : ChatType::Private,
                'phone' => $normalized->senderPhone,
                'group_id' => $normalized->groupId,
                'display_name' => $normalized->senderName,
                'is_group' => $normalized->isFromGroup(),
            ]
        );
    }

    /**
     * Find an active group binding for this message's group (if applicable).
     */
    private function findGroupBinding(WhatsAppInstance $instance, NormalizedInboundMessageData $normalized): ?WhatsAppGroupBinding
    {
        if (! $normalized->isFromGroup() || ! $normalized->groupId) {
            return null;
        }

        return WhatsAppGroupBinding::where('instance_id', $instance->id)
            ->where('group_external_id', $normalized->groupId)
            ->where('is_active', true)
            ->first();
    }
}
