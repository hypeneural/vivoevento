<?php

namespace App\Modules\EventTeam\Services;

use App\Modules\Events\Models\Event;
use App\Modules\WhatsApp\Models\WhatsAppInstance;

class EventTeamInvitationWhatsAppInstanceResolver
{
    public function resolve(Event $event): ?WhatsAppInstance
    {
        $eventInstance = $this->resolveEventDefaultInstance($event);

        if ($eventInstance) {
            return $eventInstance;
        }

        if (! $event->organization_id) {
            return null;
        }

        $baseQuery = WhatsAppInstance::query()
            ->where('organization_id', $event->organization_id)
            ->active()
            ->connected();

        $organizationDefault = (clone $baseQuery)
            ->default()
            ->first();

        if ($organizationDefault) {
            return $organizationDefault;
        }

        $connectedInstances = (clone $baseQuery)
            ->limit(2)
            ->get();

        return $connectedInstances->count() === 1
            ? $connectedInstances->first()
            : null;
    }

    private function resolveEventDefaultInstance(Event $event): ?WhatsAppInstance
    {
        $instanceId = (int) ($event->default_whatsapp_instance_id ?? 0);

        if ($instanceId < 1) {
            return null;
        }

        $instance = WhatsAppInstance::query()->find($instanceId);

        if (! $instance) {
            return null;
        }

        if ((int) $instance->organization_id !== (int) $event->organization_id) {
            return null;
        }

        if (! $instance->is_active || ! $instance->isConnected()) {
            return null;
        }

        return $instance;
    }
}
