<?php

namespace App\Modules\WhatsApp\Events;

use App\Modules\WhatsApp\Clients\DTOs\NormalizedInboundMessageData;
use App\Modules\WhatsApp\Models\WhatsAppGroupBinding;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when a new inbound WhatsApp message is received and persisted.
 *
 * Listeners can use this to:
 * - Route media to the InboundMedia/MediaProcessing pipeline
 * - Trigger auto-reactions
 * - Trigger auto-replies
 * - Update analytics
 */
class WhatsAppMessageReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly WhatsAppMessage $message,
        public readonly NormalizedInboundMessageData $normalized,
        public readonly ?WhatsAppGroupBinding $groupBinding = null,
    ) {}

    /**
     * Whether this message is from a group bound to an event.
     */
    public function isBoundToEvent(): bool
    {
        return $this->groupBinding !== null && $this->groupBinding->event_id !== null;
    }

    /**
     * Whether this message contains downloadable media.
     */
    public function hasMedia(): bool
    {
        return $this->normalized->hasMedia();
    }
}
