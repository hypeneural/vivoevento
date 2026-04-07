<?php

namespace App\Modules\Telegram\Services;

use App\Modules\InboundMedia\Models\InboundMessage;

class TelegramFeedbackContextResolver
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

        if (! is_array($context) || data_get($context, 'intake_source') !== 'telegram') {
            return null;
        }

        return $context;
    }
}
