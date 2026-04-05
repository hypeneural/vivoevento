<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\WhatsApp\Clients\DTOs\RemoveReactionData;
use App\Modules\WhatsApp\Clients\DTOs\SendAudioData;
use App\Modules\WhatsApp\Clients\DTOs\SendCarouselData;
use App\Modules\WhatsApp\Clients\DTOs\SendImageData;
use App\Modules\WhatsApp\Clients\DTOs\SendPixButtonData;
use App\Modules\WhatsApp\Clients\DTOs\SendReactionData;
use App\Modules\WhatsApp\Clients\DTOs\SendTextData;
use App\Modules\WhatsApp\Enums\MessageDirection;
use App\Modules\WhatsApp\Enums\MessageStatus;
use App\Modules\WhatsApp\Enums\MessageType;
use App\Modules\WhatsApp\Exceptions\InstanceNotConnectedException;
use App\Modules\WhatsApp\Jobs\SendWhatsAppMessageJob;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use App\Modules\WhatsApp\Models\WhatsAppMessage;

/**
 * Central messaging service — orchestrates all outbound WhatsApp message sending.
 *
 * Flow:
 * 1. Validate instance is connected
 * 2. Normalize destination
 * 3. Create WhatsAppMessage record (queued)
 * 4. Dispatch SendWhatsAppMessageJob to whatsapp-send queue
 * 5. Return message record immediately (async)
 */
class WhatsAppMessagingService
{
    public function __construct(
        private readonly WhatsAppTargetNormalizer $targetNormalizer,
    ) {}

    // ─── Send Methods ──────────────────────────────────────

    public function sendText(WhatsAppInstance $instance, SendTextData $data): WhatsAppMessage
    {
        $this->ensureConnected($instance);

        $message = $this->createOutboundMessage($instance, MessageType::Text, $data->phone, [
            'text_body' => $data->message,
            'payload_json' => [
                'phone' => $data->phone,
                'message' => $data->message,
                'delayMessage' => $data->delayMessage,
                'delayTyping' => $data->delayTyping,
            ],
        ]);

        SendWhatsAppMessageJob::dispatch($message->id, 'sendText', $data);

        return $message;
    }

    public function sendImage(WhatsAppInstance $instance, SendImageData $data): WhatsAppMessage
    {
        $this->ensureConnected($instance);

        $message = $this->createOutboundMessage($instance, MessageType::Image, $data->phone, [
            'text_body' => $data->caption,
            'media_url' => $data->image,
            'payload_json' => [
                'phone' => $data->phone,
                'image' => $data->image,
                'caption' => $data->caption,
            ],
        ]);

        SendWhatsAppMessageJob::dispatch($message->id, 'sendImage', $data);

        return $message;
    }

    public function sendAudio(WhatsAppInstance $instance, SendAudioData $data): WhatsAppMessage
    {
        $this->ensureConnected($instance);

        $message = $this->createOutboundMessage($instance, MessageType::Audio, $data->phone, [
            'media_url' => $data->audio,
            'payload_json' => [
                'phone' => $data->phone,
                'audio' => $data->audio,
            ],
        ]);

        SendWhatsAppMessageJob::dispatch($message->id, 'sendAudio', $data);

        return $message;
    }

    public function sendReaction(WhatsAppInstance $instance, SendReactionData $data): WhatsAppMessage
    {
        $this->ensureConnected($instance);

        $message = $this->createOutboundMessage($instance, MessageType::Reaction, $data->phone, [
            'text_body' => $data->reaction,
            'reply_to_provider_message_id' => $data->messageId,
            'payload_json' => [
                'phone' => $data->phone,
                'reaction' => $data->reaction,
                'messageId' => $data->messageId,
                'fromMe' => $data->fromMe,
            ],
        ]);

        SendWhatsAppMessageJob::dispatch($message->id, 'sendReaction', $data);

        return $message;
    }

    public function removeReaction(WhatsAppInstance $instance, RemoveReactionData $data): WhatsAppMessage
    {
        $this->ensureConnected($instance);

        $message = $this->createOutboundMessage($instance, MessageType::Reaction, $data->phone, [
            'reply_to_provider_message_id' => $data->messageId,
            'payload_json' => [
                'phone' => $data->phone,
                'messageId' => $data->messageId,
                'fromMe' => $data->fromMe,
            ],
        ]);

        SendWhatsAppMessageJob::dispatch($message->id, 'removeReaction', $data);

        return $message;
    }

    public function sendCarousel(WhatsAppInstance $instance, SendCarouselData $data): WhatsAppMessage
    {
        $this->ensureConnected($instance);

        $message = $this->createOutboundMessage($instance, MessageType::Carousel, $data->phone, [
            'text_body' => $data->message,
            'payload_json' => [
                'phone' => $data->phone,
                'message' => $data->message,
                'carousel' => $data->cards,
            ],
        ]);

        SendWhatsAppMessageJob::dispatch($message->id, 'sendCarousel', $data);

        return $message;
    }

    public function sendPixButton(WhatsAppInstance $instance, SendPixButtonData $data): WhatsAppMessage
    {
        $this->ensureConnected($instance);

        $message = $this->createOutboundMessage($instance, MessageType::Pix, $data->phone, [
            'text_body' => $data->pixKey,
            'payload_json' => [
                'phone' => $data->phone,
                'pixKey' => $data->pixKey,
                'type' => $data->type,
                'merchantName' => $data->merchantName,
            ],
        ]);

        SendWhatsAppMessageJob::dispatch($message->id, 'sendPixButton', $data);

        return $message;
    }

    // ─── Internal ──────────────────────────────────────────

    private function ensureConnected(WhatsAppInstance $instance): void
    {
        if (! $instance->isConnected()) {
            throw new InstanceNotConnectedException(
                "Instance '{$instance->name}' (ID: {$instance->id}) is not connected. Status: {$instance->status->value}"
            );
        }
    }

    private function createOutboundMessage(
        WhatsAppInstance $instance,
        MessageType $type,
        string $recipientPhone,
        array $extra = [],
    ): WhatsAppMessage {
        $target = $this->targetNormalizer->normalize($recipientPhone);

        return WhatsAppMessage::create(array_merge([
            'instance_id' => $instance->id,
            'direction' => MessageDirection::Outbound,
            'type' => $type,
            'status' => MessageStatus::Queued,
            'recipient_phone' => $target['normalized'],
        ], $extra));
    }
}
