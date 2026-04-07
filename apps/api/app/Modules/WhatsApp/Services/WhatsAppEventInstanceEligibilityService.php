<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\Events\Models\Event;
use App\Modules\WhatsApp\Models\WhatsAppInstance;

class WhatsAppEventInstanceEligibilityService
{
    public function allowsInboundOnInstance(Event $event, WhatsAppInstance $instance): bool
    {
        return (int) $event->default_whatsapp_instance_id === $instance->id
            && ! $this->hasDedicatedConflict($event);
    }

    public function hasDedicatedConflict(Event $event): bool
    {
        if ($event->whatsapp_instance_mode !== 'dedicated') {
            return false;
        }

        $instanceId = (int) ($event->default_whatsapp_instance_id ?? 0);

        if ($instanceId < 1) {
            return false;
        }

        return Event::query()
            ->where('id', '!=', $event->id)
            ->where('default_whatsapp_instance_id', $instanceId)
            ->where('whatsapp_instance_mode', 'dedicated')
            ->exists();
    }
}
