<?php

namespace App\Modules\Telegram\Services;

use App\Modules\Channels\Enums\ChannelType;
use App\Modules\Channels\Models\EventChannel;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Services\EventMediaSenderBlacklistService;
use App\Modules\InboundMedia\Models\ChannelWebhookLog;
use App\Modules\Telegram\Models\TelegramInboxSession;
use App\Modules\Telegram\Support\TelegramPrivateCommandParser;

class TelegramPrivateSessionService
{
    public function __construct(
        private readonly TelegramPrivateCommandParser $commands,
        private readonly EventMediaSenderBlacklistService $blacklists,
    ) {}

    public function handleTextCommand(ChannelWebhookLog $webhookLog, array $inspection): ?string
    {
        if (($inspection['message_type'] ?? null) !== 'text') {
            return null;
        }

        $text = $inspection['text'] ?? null;
        $chatId = $inspection['chat_id'] ?? null;
        $senderId = $inspection['sender_id'] ?? $chatId;
        $messageId = $inspection['message_id'] ?? null;

        if (! is_string($text) || ! is_string($chatId) || ! is_string($senderId) || ! is_string($messageId)) {
            return null;
        }

        if ($this->commands->isExitCommand($text)) {
            return $this->closeActiveSession($webhookLog, $chatId, $messageId);
        }

        $activationCode = $this->commands->extractActivationCode($text);

        if ($activationCode === null && $this->findActiveSession($chatId) === null) {
            $activationCode = $this->commands->extractStandaloneActivationCode($text);
        }

        if ($activationCode === null) {
            return null;
        }

        return $this->activateSession($webhookLog, $inspection, $activationCode);
    }

    public function findActiveSession(string $chatId): ?TelegramInboxSession
    {
        return TelegramInboxSession::query()
            ->with(['event.modules', 'channel'])
            ->active()
            ->where('chat_external_id', $chatId)
            ->latest('id')
            ->first();
    }

    public function findLatestSession(string $chatId): ?TelegramInboxSession
    {
        return TelegramInboxSession::query()
            ->with(['event.modules', 'channel'])
            ->where('chat_external_id', $chatId)
            ->latest('id')
            ->first();
    }

    public function touchSession(TelegramInboxSession $session, array $inspection): void
    {
        if ($session->status !== 'active') {
            return;
        }

        $session->update([
            'last_inbound_provider_message_id' => $inspection['message_id'] ?? $session->last_inbound_provider_message_id,
            'last_interaction_at' => now(),
        ]);
    }

    private function activateSession(ChannelWebhookLog $webhookLog, array $inspection, string $activationCode): string
    {
        $channel = $this->findChannelByCode($activationCode);
        $event = $channel?->event;

        if (! $channel || ! $event || ! $this->eventAllowsTelegramPrivateIntake($event)) {
            $webhookLog->update([
                'routing_status' => 'ignored',
                'error_message' => 'invalid_activation_code',
            ]);

            return 'invalid_activation_code';
        }

        $chatId = (string) $inspection['chat_id'];
        $senderId = (string) ($inspection['sender_id'] ?? $chatId);
        $messageId = (string) $inspection['message_id'];

        if ($this->blacklists->matchCandidates($event, [
            'external_id' => [$senderId, $chatId],
            'phone' => [],
            'lid' => [],
        ])) {
            $webhookLog->update([
                'event_channel_id' => $channel->id,
                'routing_status' => 'blocked',
                'error_message' => 'sender_blacklisted',
            ]);

            return 'sender_blocked';
        }

        $ttlMinutes = max(1, (int) data_get($channel->config_json, 'session_ttl_minutes', 180));

        TelegramInboxSession::query()
            ->active()
            ->where('chat_external_id', $chatId)
            ->where(function ($query) use ($event, $channel) {
                $query->where('event_id', '!=', $event->id)
                    ->orWhere('event_channel_id', '!=', $channel->id);
            })
            ->update([
                'status' => 'closed',
                'closed_at' => now(),
                'last_inbound_provider_message_id' => $messageId,
                'last_interaction_at' => now(),
            ]);

        $session = TelegramInboxSession::query()
            ->where('chat_external_id', $chatId)
            ->where('event_id', $event->id)
            ->where('event_channel_id', $channel->id)
            ->latest('id')
            ->first();

        if (! $session) {
            $session = new TelegramInboxSession([
                'organization_id' => $event->organization_id,
                'event_id' => $event->id,
                'event_channel_id' => $channel->id,
                'chat_external_id' => $chatId,
                'sender_external_id' => $senderId,
            ]);
        }

        $session->fill([
            'sender_external_id' => $senderId,
            'sender_name' => $inspection['sender_name'] ?? null,
            'status' => 'active',
            'activated_by_provider_message_id' => $messageId,
            'last_inbound_provider_message_id' => $messageId,
            'activated_at' => now(),
            'last_interaction_at' => now(),
            'expires_at' => now()->addMinutes($ttlMinutes),
            'closed_at' => null,
            'metadata_json' => [
                'media_inbox_code' => $activationCode,
                'telegram_chat_id' => $chatId,
                'telegram_user_id' => $senderId,
            ],
        ]);
        $session->save();

        $webhookLog->update([
            'event_channel_id' => $channel->id,
            'routing_status' => 'session_activated',
            'error_message' => null,
        ]);

        return 'session_activated';
    }

    private function closeActiveSession(ChannelWebhookLog $webhookLog, string $chatId, string $messageId): string
    {
        $session = $this->findActiveSession($chatId);

        if (! $session) {
            $webhookLog->update([
                'routing_status' => 'ignored',
                'error_message' => 'no_active_session',
            ]);

            return 'no_active_session';
        }

        $session->update([
            'status' => 'closed',
            'closed_at' => now(),
            'last_inbound_provider_message_id' => $messageId,
            'last_interaction_at' => now(),
        ]);

        $webhookLog->update([
            'event_channel_id' => $session->event_channel_id,
            'routing_status' => 'session_closed',
            'error_message' => null,
        ]);

        return 'session_closed';
    }

    private function findChannelByCode(string $activationCode): ?EventChannel
    {
        return EventChannel::query()
            ->with(['event.modules'])
            ->where('channel_type', ChannelType::TelegramBot->value)
            ->where('provider', 'telegram')
            ->where('status', 'active')
            ->where('external_id', $activationCode)
            ->latest('id')
            ->first();
    }

    private function eventAllowsTelegramPrivateIntake(Event $event): bool
    {
        $event->loadMissing('modules');

        return $event->isActive()
            && $event->isModuleEnabled('live')
            && (bool) data_get($event->current_entitlements_json, 'channels.telegram.enabled', false);
    }
}
