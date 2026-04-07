<?php

namespace App\Modules\Events\Support;

use App\Modules\Channels\Enums\ChannelType;
use App\Modules\Channels\Models\EventChannel;
use App\Modules\Events\Models\Event;

class EventIntakeChannelsStateBuilder
{
    /**
     * @return array{
     *   intake_defaults: array{whatsapp_instance_id: ?int, whatsapp_instance_mode: ?string},
     *   intake_channels: array<string, mixed>
     * }
     */
    public function build(Event $event): array
    {
        $event->loadMissing(['channels', 'whatsappGroupBindings']);

        $groupChannel = $this->findChannel($event, ChannelType::WhatsAppGroup);
        $directChannel = $this->findChannel($event, ChannelType::WhatsAppDirect);
        $uploadChannel = $this->findChannel($event, ChannelType::PublicUploadLink);
        $telegramChannel = $this->findChannel($event, ChannelType::TelegramBot);

        $groupConfig = $groupChannel?->config_json ?? [];
        $groups = $groupConfig['groups'] ?? [];

        if ($groups === []) {
            $groups = $event->whatsappGroupBindings
                ->where('is_active', true)
                ->map(fn ($binding) => [
                    'group_external_id' => $binding->group_external_id,
                    'group_name' => $binding->group_name,
                    'is_active' => (bool) $binding->is_active,
                    'auto_feedback_enabled' => (bool) data_get($binding->metadata_json, 'auto_feedback_enabled', false),
                ])
                ->values()
                ->all();
        }

        return [
            'intake_defaults' => [
                'whatsapp_instance_id' => $event->default_whatsapp_instance_id,
                'whatsapp_instance_mode' => $event->whatsapp_instance_mode ?: 'shared',
            ],
            'intake_channels' => [
                'whatsapp_groups' => [
                    'enabled' => $groupChannel !== null && $groupChannel->status === 'active',
                    'groups' => array_values($groups),
                ],
                'whatsapp_direct' => [
                    'enabled' => $directChannel !== null && $directChannel->status === 'active',
                    'media_inbox_code' => data_get($directChannel?->config_json, 'media_inbox_code'),
                    'session_ttl_minutes' => data_get($directChannel?->config_json, 'session_ttl_minutes'),
                ],
                'public_upload' => [
                    'enabled' => $uploadChannel !== null && $uploadChannel->status === 'active',
                ],
                'telegram' => [
                    'enabled' => $telegramChannel !== null && $telegramChannel->status === 'active',
                    'bot_username' => data_get($telegramChannel?->config_json, 'bot_username'),
                    'media_inbox_code' => data_get($telegramChannel?->config_json, 'media_inbox_code'),
                    'session_ttl_minutes' => data_get($telegramChannel?->config_json, 'session_ttl_minutes'),
                ],
            ],
        ];
    }

    private function findChannel(Event $event, ChannelType $channelType): ?EventChannel
    {
        return $event->channels->first(
            fn (EventChannel $channel) => $channel->channel_type === $channelType
        );
    }
}
