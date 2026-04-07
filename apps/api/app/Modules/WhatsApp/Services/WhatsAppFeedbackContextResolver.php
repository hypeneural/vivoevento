<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\InboundMedia\Models\InboundMessage;

class WhatsAppFeedbackContextResolver
{
    /**
     * @return array<string, mixed>|null
     */
    public function fromInboundMessage(InboundMessage $inboundMessage): ?array
    {
        $payload = is_array($inboundMessage->normalized_payload_json)
            ? $inboundMessage->normalized_payload_json
            : [];

        $context = data_get($payload, '_event_context');

        if (! is_array($context)) {
            return null;
        }

        return $context;
    }
}
