<?php

namespace App\Modules\Telegram\Console;

use App\Modules\Telegram\Clients\BotApi\TelegramBotApiClient;
use Illuminate\Console\Command;

class RegisterTelegramWebhookCommand extends Command
{
    protected $signature = 'telegram:webhook:set
        {url : Public HTTPS webhook URL}
        {--drop-pending : Drop pending Telegram updates during registration}';

    protected $description = 'Register the Telegram webhook with the private-only V1 allowed updates.';

    public function handle(TelegramBotApiClient $client): int
    {
        $url = (string) $this->argument('url');

        if (! $this->isAllowedPublicWebhookUrl($url)) {
            $this->error('Telegram webhook URL must be a public HTTPS URL.');

            return self::FAILURE;
        }

        $response = $client->setWebhook(
            url: $url,
            allowedUpdates: ['message', 'my_chat_member'],
            dropPendingUpdates: (bool) $this->option('drop-pending'),
        );

        if (! (bool) data_get($response, 'ok', false)) {
            $this->error('Telegram rejected the webhook registration.');

            return self::FAILURE;
        }

        $this->info('Telegram webhook registered.');

        return self::SUCCESS;
    }

    private function isAllowedPublicWebhookUrl(string $url): bool
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (($parts['scheme'] ?? null) !== 'https' || $host === '') {
            return false;
        }

        return ! in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }
}
