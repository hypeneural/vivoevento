<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\Channels\Enums\ChannelType;
use App\Modules\Channels\Models\EventChannel;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Services\EventMediaSenderBlacklistService;
use App\Modules\WhatsApp\Clients\DTOs\NormalizedInboundMessageData;
use App\Modules\WhatsApp\Models\WhatsAppGroupBinding;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use App\Modules\WhatsApp\Models\WhatsAppMessage;

class WhatsAppGroupActivationService
{
    public function __construct(
        private readonly EventMediaSenderBlacklistService $blacklists,
        private readonly WhatsAppFeedbackAutomationService $feedback,
        private readonly WhatsAppMessagingService $messaging,
        private readonly WhatsAppEventInstanceEligibilityService $instanceEligibility,
    ) {}

    public function handleTextMessage(
        WhatsAppInstance $instance,
        WhatsAppMessage $message,
        NormalizedInboundMessageData $normalized,
    ): bool {
        if (! $normalized->isFromGroup() || $normalized->messageType !== 'text' || ($normalized->fromMe ?? false) === true) {
            return false;
        }

        $groupBindCode = $this->extractGroupBindCode($normalized->normalizedText());

        if ($groupBindCode === null) {
            return false;
        }

        $channel = EventChannel::query()
            ->where('channel_type', ChannelType::WhatsAppGroup->value)
            ->where('provider', $instance->providerKeyValue())
            ->where('external_id', $groupBindCode)
            ->where('status', 'active')
            ->first();

        if (! $channel) {
            return true;
        }

        $channel->loadMissing('event.modules', 'event.channels');
        $event = $channel->event;

        if (! $event || ! $this->eventAllowsAutoBinding($event, $instance, $normalized->groupId)) {
            return true;
        }

        if ($this->blacklists->matchNormalized($event, $normalized)) {
            $this->feedback->sendRejectedFeedback($event, $instance, [
                'provider_message_id' => $normalized->messageId,
                'chat_external_id' => $normalized->chatId,
                'sender_external_id' => $normalized->senderExternalId(),
                'intake_source' => 'whatsapp_group',
            ], phase: 'blocked');

            return true;
        }

        WhatsAppGroupBinding::query()->updateOrCreate(
            [
                'instance_id' => $instance->id,
                'group_external_id' => $normalized->groupId,
                'binding_type' => \App\Modules\WhatsApp\Enums\GroupBindingType::EventGallery->value,
            ],
            [
                'organization_id' => $event->organization_id,
                'event_id' => $event->id,
                'group_name' => $normalized->chatName,
                'is_active' => true,
                'metadata_json' => [],
            ],
        );

        $this->syncChannelGroupList($channel, $normalized->groupId, $normalized->chatName);

        $this->messaging->sendText(
            $instance,
            new \App\Modules\WhatsApp\Clients\DTOs\SendTextData(
                phone: $normalized->chatId,
                message: "✨ Vinculo realizado: {$event->title}\n\n📸 Agora esse grupo pode enviar fotos e videos para o evento.",
                messageId: $normalized->messageId,
            ),
        );

        return true;
    }

    private function eventAllowsAutoBinding(Event $event, WhatsAppInstance $instance, ?string $groupExternalId): bool
    {
        $maxGroups = data_get($event->current_entitlements_json, 'channels.whatsapp_groups.max');

        if (! $event->isActive()
            || ! $event->isModuleEnabled('live')
            || ! $this->instanceEligibility->allowsInboundOnInstance($event, $instance)
            || ! (bool) data_get($event->current_entitlements_json, 'channels.whatsapp_groups.enabled', false)
        ) {
            return false;
        }

        if ($maxGroups === null) {
            return true;
        }

        $activeBindingsCount = WhatsAppGroupBinding::query()
            ->where('event_id', $event->id)
            ->where('binding_type', \App\Modules\WhatsApp\Enums\GroupBindingType::EventGallery->value)
            ->where('is_active', true)
            ->count();

        return $activeBindingsCount < (int) $maxGroups
            || WhatsAppGroupBinding::query()
                ->where('event_id', $event->id)
                ->where('instance_id', $instance->id)
                ->where('group_external_id', $groupExternalId)
                ->where('binding_type', \App\Modules\WhatsApp\Enums\GroupBindingType::EventGallery->value)
                ->where('is_active', true)
                ->exists();
    }

    private function extractGroupBindCode(?string $text): ?string
    {
        if (! is_string($text)) {
            return null;
        }

        if (! preg_match('/^\s*#ATIVAR#([A-Z0-9_-]{4,80})\s*$/i', trim($text), $matches)) {
            return null;
        }

        return strtoupper(trim($matches[1]));
    }

    private function syncChannelGroupList(EventChannel $channel, string $groupExternalId, ?string $groupName): void
    {
        $config = is_array($channel->config_json) ? $channel->config_json : [];
        $groups = collect((array) ($config['groups'] ?? []));

        $updated = $groups
            ->reject(fn ($group) => data_get($group, 'group_external_id') === $groupExternalId)
            ->push([
                'group_external_id' => $groupExternalId,
                'group_name' => $groupName,
                'is_active' => true,
                'auto_feedback_enabled' => true,
            ])
            ->values()
            ->all();

        $channel->forceFill([
            'config_json' => array_merge($config, [
                'group_bind_code' => $channel->external_id,
                'groups' => $updated,
            ]),
        ])->save();
    }
}
