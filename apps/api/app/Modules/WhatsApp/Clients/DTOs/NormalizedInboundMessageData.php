<?php

namespace App\Modules\WhatsApp\Clients\DTOs;

use Carbon\CarbonImmutable;

/**
 * Normalized inbound message DTO — the heart of multi-provider compatibility.
 *
 * Every provider normalizer MUST produce this DTO from its raw webhook payload.
 * The rest of the system only deals with this format.
 */
final readonly class NormalizedInboundMessageData
{
    public function __construct(
        public string $providerKey,
        public string $instanceExternalId,
        public string $eventType,          // 'message', 'status', 'delivery', 'group_join', etc.
        public string $messageId,
        public string $chatId,
        public string $chatType,           // 'private' | 'group'
        public ?string $groupId,
        public ?string $senderPhone,
        public ?string $senderName,
        public string $messageType,        // 'text', 'image', 'audio', 'reaction', etc.
        public ?string $text,
        public ?string $mediaUrl,
        public ?string $mimeType,
        public ?string $caption,
        public CarbonImmutable $occurredAt,
        public array $rawPayload,
        public ?string $callbackType = null,
        public ?bool $fromMe = null,
        public ?string $participantPhone = null,
        public ?string $participantLid = null,
        public ?string $connectedPhone = null,
        public ?string $chatName = null,
    ) {}

    public function isFromGroup(): bool
    {
        return $this->chatType === 'group';
    }

    public function hasMedia(): bool
    {
        return $this->mediaUrl !== null;
    }

    public function senderExternalId(): string
    {
        return $this->participantLid
            ?? $this->senderPhone
            ?? $this->chatId;
    }

    public function normalizedText(): ?string
    {
        $text = $this->text;

        if ($text === null) {
            return null;
        }

        $normalized = trim($text);

        return $normalized === '' ? null : $normalized;
    }

    public function chatDisplayName(): ?string
    {
        if ($this->isFromGroup()) {
            return $this->chatName ?? $this->senderName;
        }

        return $this->senderName ?? $this->chatName;
    }

    public function toArray(): array
    {
        return [
            'provider_key' => $this->providerKey,
            'instance_external_id' => $this->instanceExternalId,
            'event_type' => $this->eventType,
            'message_id' => $this->messageId,
            'chat_id' => $this->chatId,
            'chat_type' => $this->chatType,
            'group_id' => $this->groupId,
            'sender_phone' => $this->senderPhone,
            'sender_name' => $this->senderName,
            'message_type' => $this->messageType,
            'text' => $this->text,
            'media_url' => $this->mediaUrl,
            'mime_type' => $this->mimeType,
            'caption' => $this->caption,
            'occurred_at' => $this->occurredAt->toIso8601String(),
            'callback_type' => $this->callbackType,
            'from_me' => $this->fromMe,
            'participant_phone' => $this->participantPhone,
            'participant_lid' => $this->participantLid,
            'connected_phone' => $this->connectedPhone,
            'chat_name' => $this->chatName,
        ];
    }
}
