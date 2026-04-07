<?php

namespace App\Modules\Telegram\Services;

use App\Modules\Telegram\Clients\BotApi\TelegramBotApiClient;

class TelegramBotHealthcheckService
{
    public function __construct(
        private readonly TelegramBotApiClient $client,
    ) {}

    public function inspect(): array
    {
        $me = $this->client->getMe();
        $webhook = $this->client->getWebhookInfo();

        return [
            'bot' => [
                'ok' => (bool) data_get($me, 'ok', false),
                'id' => $this->stringify(data_get($me, 'result.id')),
                'username' => data_get($me, 'result.username'),
                'is_bot' => (bool) data_get($me, 'result.is_bot', false),
                'can_join_groups' => (bool) data_get($me, 'result.can_join_groups', false),
                'can_read_all_group_messages' => (bool) data_get($me, 'result.can_read_all_group_messages', false),
            ],
            'webhook' => [
                'ok' => (bool) data_get($webhook, 'ok', false),
                'url' => data_get($webhook, 'result.url'),
                'pending_update_count' => (int) data_get($webhook, 'result.pending_update_count', 0),
                'has_custom_certificate' => (bool) data_get($webhook, 'result.has_custom_certificate', false),
                'ip_address' => $this->nullIfBlank(data_get($webhook, 'result.ip_address')),
                'last_error_at' => $this->timestampToIsoString(data_get($webhook, 'result.last_error_date')),
                'last_error_message' => $this->nullIfBlank(data_get($webhook, 'result.last_error_message')),
                'max_connections' => data_get($webhook, 'result.max_connections'),
                'allowed_updates' => array_values(array_filter((array) data_get($webhook, 'result.allowed_updates', []), 'is_string')),
            ],
        ];
    }

    public function inspectSafely(): array
    {
        $snapshot = [
            'configured' => $this->isConfigured(),
            'healthy' => false,
            'error_message' => null,
            'bot' => [
                'ok' => false,
                'id' => null,
                'username' => null,
                'is_bot' => false,
                'can_join_groups' => false,
                'can_read_all_group_messages' => false,
            ],
            'webhook' => [
                'ok' => false,
                'url' => null,
                'pending_update_count' => 0,
                'has_custom_certificate' => false,
                'ip_address' => null,
                'last_error_at' => null,
                'last_error_message' => null,
                'max_connections' => null,
                'allowed_updates' => [],
            ],
        ];

        if (! $snapshot['configured']) {
            $snapshot['error_message'] = 'services.telegram.bot_token is not configured.';

            return $snapshot;
        }

        try {
            $inspected = $this->inspect();

            return array_merge($snapshot, $inspected, [
                'healthy' => (bool) data_get($inspected, 'bot.ok', false)
                    && (bool) data_get($inspected, 'webhook.ok', false)
                    && blank(data_get($inspected, 'webhook.last_error_message')),
            ]);
        } catch (\Throwable $exception) {
            $snapshot['error_message'] = $exception->getMessage();

            return $snapshot;
        }
    }

    private function isConfigured(): bool
    {
        return filled(config('services.telegram.base_url'))
            && filled(config('services.telegram.bot_token'));
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
