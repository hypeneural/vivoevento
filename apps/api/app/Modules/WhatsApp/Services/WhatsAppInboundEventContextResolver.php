<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\Channels\Enums\ChannelType;
use App\Modules\Channels\Models\EventChannel;
use App\Modules\Events\Models\Event;
use App\Modules\WhatsApp\Clients\DTOs\NormalizedInboundMessageData;
use App\Modules\WhatsApp\Models\WhatsAppGroupBinding;
use App\Modules\WhatsApp\Models\WhatsAppInboxSession;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use App\Modules\WhatsApp\Support\ResolvedWhatsAppEventIntakeContext;

class WhatsAppInboundEventContextResolver
{
    public function __construct(
        private readonly WhatsAppDirectIntakeSessionService $directSessions,
        private readonly WhatsAppEventInstanceEligibilityService $instanceEligibility,
    ) {}

    public function resolve(
        WhatsAppInstance $instance,
        NormalizedInboundMessageData $normalized,
        ?WhatsAppGroupBinding $groupBinding = null,
    ): ?ResolvedWhatsAppEventIntakeContext {
        if (($normalized->fromMe ?? false) === true) {
            return null;
        }

        if ($normalized->isFromGroup()) {
            return $this->resolveGroupContext($instance, $normalized, $groupBinding);
        }

        return $this->resolveDirectContext($instance, $normalized);
    }

    private function resolveGroupContext(
        WhatsAppInstance $instance,
        NormalizedInboundMessageData $normalized,
        ?WhatsAppGroupBinding $groupBinding,
    ): ?ResolvedWhatsAppEventIntakeContext {
        if (! $groupBinding || ! $groupBinding->is_active || $groupBinding->event_id === null) {
            return null;
        }

        $groupBinding->loadMissing(['event.modules', 'event.channels']);

        $event = $groupBinding->event;

        if (! $event || ! $this->eventAllowsChannel($event, ChannelType::WhatsAppGroup, $instance)) {
            return null;
        }

        $channel = $this->findActiveChannel($event, ChannelType::WhatsAppGroup);

        if (! $channel) {
            return null;
        }

        return new ResolvedWhatsAppEventIntakeContext(
            event: $event,
            eventChannel: $channel,
            intakeSource: 'whatsapp_group',
            groupBinding: $groupBinding,
        );
    }

    private function resolveDirectContext(
        WhatsAppInstance $instance,
        NormalizedInboundMessageData $normalized,
    ): ?ResolvedWhatsAppEventIntakeContext {
        $session = $this->directSessions->findActiveSession($instance, $normalized);

        if (! $session) {
            return null;
        }

        $session->loadMissing(['event.modules', 'event.channels', 'channel']);

        $event = $session->event;
        $channel = $session->channel;

        if (! $event || ! $channel || ! $this->eventAllowsChannel($event, ChannelType::WhatsAppDirect, $instance)) {
            return null;
        }

        $this->directSessions->touchSession($session, $normalized);

        return new ResolvedWhatsAppEventIntakeContext(
            event: $event,
            eventChannel: $channel,
            intakeSource: 'whatsapp_direct',
            inboxSession: $session,
        );
    }

    private function eventAllowsChannel(Event $event, ChannelType $channelType, WhatsAppInstance $instance): bool
    {
        $event->loadMissing(['modules', 'channels']);

        $entitlementPath = match ($channelType) {
            ChannelType::WhatsAppGroup => 'channels.whatsapp_groups.enabled',
            ChannelType::WhatsAppDirect => 'channels.whatsapp_direct.enabled',
            default => null,
        };

        if ($entitlementPath === null) {
            return false;
        }

        return $event->isActive()
            && $event->isModuleEnabled('live')
            && $this->instanceEligibility->allowsInboundOnInstance($event, $instance)
            && (bool) data_get($event->current_entitlements_json, $entitlementPath, false);
    }

    private function findActiveChannel(Event $event, ChannelType $channelType): ?EventChannel
    {
        if ($event->relationLoaded('channels')) {
            return $event->channels->first(
                fn (EventChannel $channel) => $channel->channel_type === $channelType && $channel->status === 'active'
            );
        }

        return $event->channels()
            ->where('channel_type', $channelType->value)
            ->where('status', 'active')
            ->first();
    }
}
