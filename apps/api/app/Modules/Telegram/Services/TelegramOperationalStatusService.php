<?php

namespace App\Modules\Telegram\Services;

use App\Modules\Channels\Enums\ChannelType;
use App\Modules\Channels\Models\EventChannel;
use App\Modules\Events\Models\Event;
use App\Modules\InboundMedia\Models\ChannelWebhookLog;

class TelegramOperationalStatusService
{
    public function __construct(
        private readonly TelegramBotHealthcheckService $healthcheck,
    ) {}

    public function build(Event $event): array
    {
        $event->loadMissing('channels');

        /** @var EventChannel|null $channel */
        $channel = $event->channels->first(
            fn (EventChannel $candidate) => $candidate->channel_type === ChannelType::TelegramBot
        );

        $snapshot = $this->healthcheck->inspectSafely();
        $expectedAllowedUpdates = array_values(array_filter((array) data_get($channel?->config_json, 'v1_allowed_updates', []), 'is_string'));
        $actualAllowedUpdates = array_values(array_filter((array) data_get($snapshot, 'webhook.allowed_updates', []), 'is_string'));

        return [
            'enabled' => $channel !== null && $channel->status === 'active',
            'configured' => (bool) data_get($snapshot, 'configured', false),
            'healthy' => (bool) data_get($snapshot, 'healthy', false),
            'error_message' => data_get($snapshot, 'error_message'),
            'channel' => $channel ? [
                'id' => $channel->id,
                'status' => $channel->status,
                'bot_username' => data_get($channel->config_json, 'bot_username'),
                'media_inbox_code' => data_get($channel->config_json, 'media_inbox_code'),
                'session_ttl_minutes' => data_get($channel->config_json, 'session_ttl_minutes'),
                'allow_private' => (bool) data_get($channel->config_json, 'allow_private', false),
                'v1_allowed_updates' => $expectedAllowedUpdates,
            ] : null,
            'bot' => data_get($snapshot, 'bot', []),
            'webhook' => [
                ...((array) data_get($snapshot, 'webhook', [])),
                'expected_allowed_updates' => $expectedAllowedUpdates,
                'matches_expected_allowed_updates' => $this->matchesAllowedUpdates($expectedAllowedUpdates, $actualAllowedUpdates),
            ],
            'recent_operational_signals' => $channel
                ? $this->recentOperationalSignals($channel)
                : [],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentOperationalSignals(EventChannel $channel): array
    {
        return ChannelWebhookLog::query()
            ->where('provider', 'telegram')
            ->where('event_channel_id', $channel->id)
            ->where('detected_type', 'my_chat_member')
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (ChannelWebhookLog $log) => $this->mapOperationalSignal($log))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapOperationalSignal(ChannelWebhookLog $log): array
    {
        $payload = is_array($log->payload_json) ? $log->payload_json : [];
        $signalPayload = is_array(data_get($payload, 'my_chat_member'))
            ? (array) data_get($payload, 'my_chat_member')
            : [];

        return [
            'id' => $log->id,
            'provider_update_id' => $log->provider_update_id,
            'chat_external_id' => $this->stringify(data_get($signalPayload, 'chat.id')),
            'sender_external_id' => $this->stringify(data_get($signalPayload, 'from.id')),
            'sender_name' => $this->senderName(data_get($signalPayload, 'from')),
            'signal' => $log->error_message,
            'old_status' => $this->nullIfBlank(data_get($signalPayload, 'old_chat_member.status')),
            'new_status' => $this->nullIfBlank(data_get($signalPayload, 'new_chat_member.status')),
            'occurred_at' => $this->timestampToIsoString(data_get($signalPayload, 'date')),
            'created_at' => $log->created_at?->toISOString(),
        ];
    }

    /**
     * @param  array<int, string>  $expectedAllowedUpdates
     * @param  array<int, string>  $actualAllowedUpdates
     */
    private function matchesAllowedUpdates(array $expectedAllowedUpdates, array $actualAllowedUpdates): bool
    {
        sort($expectedAllowedUpdates);
        sort($actualAllowedUpdates);

        return $expectedAllowedUpdates === $actualAllowedUpdates;
    }

    private function senderName(mixed $from): ?string
    {
        if (! is_array($from)) {
            return null;
        }

        $name = trim(implode(' ', array_filter([
            $from['first_name'] ?? null,
            $from['last_name'] ?? null,
        ], fn ($part) => is_string($part) && trim($part) !== '')));

        if ($name !== '') {
            return $name;
        }

        $username = $from['username'] ?? null;

        return is_string($username) && trim($username) !== '' ? trim($username) : null;
    }

    private function stringify(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            return $trimmed === '' ? null : $trimmed;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }

    private function nullIfBlank(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function timestampToIsoString(mixed $value): ?string
    {
        if (! is_numeric($value) || (int) $value <= 0) {
            return null;
        }

        return \Illuminate\Support\Carbon::createFromTimestamp((int) $value)->toISOString();
    }
}
