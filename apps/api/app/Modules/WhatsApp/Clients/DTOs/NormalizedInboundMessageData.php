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
    ) {}

    public function isFromGroup(): bool
    {
        return $this->chatType === 'group';
    }

    public function hasMedia(): bool
    {
        return $this->mediaUrl !== null;
    }
}
