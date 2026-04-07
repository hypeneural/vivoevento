<?php

namespace App\Modules\WhatsApp\Support;

use App\Modules\Channels\Models\EventChannel;
use App\Modules\Events\Models\Event;
use App\Modules\WhatsApp\Models\WhatsAppGroupBinding;
use App\Modules\WhatsApp\Models\WhatsAppInboxSession;

final readonly class ResolvedWhatsAppEventIntakeContext
{
    public function __construct(
        public Event $event,
        public EventChannel $eventChannel,
        public string $intakeSource,
        public ?WhatsAppGroupBinding $groupBinding = null,
        public ?WhatsAppInboxSession $inboxSession = null,
    ) {}

    public function toArray(): array
    {
        return [
            'event_id' => $this->event->id,
            'event_channel_id' => $this->eventChannel->id,
            'intake_source' => $this->intakeSource,
            'group_binding_id' => $this->groupBinding?->id,
            'inbox_session_id' => $this->inboxSession?->id,
        ];
    }
}
