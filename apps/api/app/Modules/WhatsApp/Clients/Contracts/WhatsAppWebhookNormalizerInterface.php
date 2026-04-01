<?php

namespace App\Modules\WhatsApp\Clients\Contracts;

use App\Modules\WhatsApp\Clients\DTOs\NormalizedInboundMessageData;
use App\Modules\WhatsApp\Models\WhatsAppInstance;

/**
 * Normalizes provider-specific webhook payloads into a standard internal format.
 */
interface WhatsAppWebhookNormalizerInterface
{
    /**
     * Transform a raw webhook payload into a normalized DTO.
     */
    public function normalize(array $payload, WhatsAppInstance $instance): NormalizedInboundMessageData;

    /**
     * Whether this normalizer handles the given provider.
     */
    public function supportsProvider(string $providerKey): bool;
}
