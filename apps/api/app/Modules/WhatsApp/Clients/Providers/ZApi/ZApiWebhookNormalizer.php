<?php

namespace App\Modules\WhatsApp\Clients\Providers\ZApi;

use App\Modules\WhatsApp\Clients\Contracts\WhatsAppWebhookNormalizerInterface;
use App\Modules\WhatsApp\Clients\DTOs\NormalizedInboundMessageData;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use Carbon\CarbonImmutable;

/**
 * Normalizes Z-API webhook payloads to the internal standardized format.
 *
 * Z-API sends different payload structures depending on the message type.
 * This normalizer extracts the common fields and maps them to NormalizedInboundMessageData.
 */
class ZApiWebhookNormalizer implements WhatsAppWebhookNormalizerInterface
{
    public function normalize(array $payload, WhatsAppInstance $instance): NormalizedInboundMessageData
    {
        $messageType = $this->detectMessageType($payload);
        $chatId = $this->extractChatId($payload);
        $isGroup = $this->isGroupMessage($payload);

        return new NormalizedInboundMessageData(
            providerKey: 'zapi',
            instanceExternalId: $instance->external_instance_id,
            eventType: $this->detectEventType($payload),
            messageId: $this->extractMessageId($payload),
            chatId: $chatId,
            chatType: $isGroup ? 'group' : 'private',
            groupId: $isGroup ? $chatId : null,
            senderPhone: $this->extractSenderPhone($payload),
            senderName: $this->extractSenderName($payload),
            messageType: $messageType,
            text: $this->extractText($payload, $messageType),
            mediaUrl: $this->extractMediaUrl($payload, $messageType),
            mimeType: $this->extractMimeType($payload),
            caption: $payload['caption'] ?? null,
            occurredAt: $this->extractTimestamp($payload),
            rawPayload: $payload,
        );
    }

    public function supportsProvider(string $providerKey): bool
    {
        return $providerKey === 'zapi';
    }

    // ─── Type Detection ────────────────────────────────────

    private function detectMessageType(array $payload): string
    {
        // Z-API uses different payload structures per type
        if (isset($payload['image'])) {
            return 'image';
        }
        if (isset($payload['video'])) {
            return 'video';
        }
        if (isset($payload['audio'])) {
            return 'audio';
        }
        if (isset($payload['document'])) {
            return 'document';
        }
        if (isset($payload['sticker'])) {
            return 'sticker';
        }
        if (isset($payload['reaction'])) {
            return 'reaction';
        }
        if (isset($payload['contact'])) {
            return 'contact';
        }
        if (isset($payload['text']) && isset($payload['text']['message'])) {
            return 'text';
        }
        if (isset($payload['body'])) {
            return 'text';
        }

        return 'system';
    }

    private function detectEventType(array $payload): string
    {
        // Z-API can send status updates or message events
        if (isset($payload['status'])) {
            return 'status';
        }
        if (isset($payload['ack'])) {
            return 'delivery';
        }

        return 'message';
    }

    // ─── Field Extraction ──────────────────────────────────

    private function extractMessageId(array $payload): string
    {
        return $payload['messageId']
            ?? $payload['id']['id']
            ?? $payload['ids'][0] ?? '';
    }

    private function extractChatId(array $payload): string
    {
        return $payload['chatId']
            ?? $payload['phone']
            ?? $payload['from']
            ?? '';
    }

    private function isGroupMessage(array $payload): bool
    {
        $chatId = $this->extractChatId($payload);

        // Z-API group IDs end with @g.us
        if (str_contains($chatId, '@g.us')) {
            return true;
        }

        return (bool) ($payload['isGroup'] ?? false);
    }

    private function extractSenderPhone(array $payload): ?string
    {
        return $payload['phone']
            ?? $payload['senderPhone']
            ?? $payload['from']
            ?? null;
    }

    private function extractSenderName(array $payload): ?string
    {
        return $payload['senderName']
            ?? $payload['chatName']
            ?? $payload['notifyName']
            ?? null;
    }

    private function extractText(array $payload, string $messageType): ?string
    {
        if ($messageType === 'text') {
            return $payload['text']['message']
                ?? $payload['body']
                ?? $payload['message']
                ?? null;
        }

        if ($messageType === 'reaction') {
            return $payload['reaction'] ?? null;
        }

        return $payload['caption'] ?? null;
    }

    private function extractMediaUrl(array $payload, string $messageType): ?string
    {
        $mediaTypes = ['image', 'video', 'audio', 'document', 'sticker'];

        if (in_array($messageType, $mediaTypes, true)) {
            return $payload[$messageType]['imageUrl']
                ?? $payload[$messageType]['videoUrl']
                ?? $payload[$messageType]['audioUrl']
                ?? $payload[$messageType]['documentUrl']
                ?? $payload[$messageType]['stickerUrl']
                ?? $payload[$messageType]['url']
                ?? $payload['imageUrl']
                ?? $payload['mediaUrl']
                ?? null;
        }

        return null;
    }

    private function extractMimeType(array $payload): ?string
    {
        return $payload['mimetype']
            ?? $payload['mimeType']
            ?? null;
    }

    private function extractTimestamp(array $payload): CarbonImmutable
    {
        if (isset($payload['mompiledentTimestamp'])) {
            return CarbonImmutable::createFromTimestamp($payload['momentTimestamp']);
        }

        if (isset($payload['timestamp'])) {
            return CarbonImmutable::createFromTimestamp($payload['timestamp']);
        }

        return CarbonImmutable::now();
    }
}
