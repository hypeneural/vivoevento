<?php

namespace App\Modules\Telegram\Clients\BotApi;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class TelegramBotApiClient
{
    public function getMe(): array
    {
        return $this->request()->get('/getMe')->throw()->json() ?? [];
    }

    public function getWebhookInfo(): array
    {
        return $this->request()->get('/getWebhookInfo')->throw()->json() ?? [];
    }

    public function setWebhook(
        string $url,
        array $allowedUpdates = ['message', 'my_chat_member'],
        bool $dropPendingUpdates = false,
    ): array {
        $payload = [
            'url' => $url,
            'allowed_updates' => array_values($allowedUpdates),
            'drop_pending_updates' => $dropPendingUpdates,
        ];

        $secretToken = (string) (config('services.telegram.webhook_secret_token') ?? '');

        if ($secretToken !== '') {
            $payload['secret_token'] = $secretToken;
        }

        return $this->request()->post('/setWebhook', $payload)->throw()->json() ?? [];
    }

    public function getFile(string $fileId): array
    {
        return $this->request()->post('/getFile', [
            'file_id' => $fileId,
        ])->throw()->json() ?? [];
    }

    public function sendChatAction(string $chatId, string $action): array
    {
        return $this->request()->post('/sendChatAction', [
            'chat_id' => $chatId,
            'action' => $action,
        ])->throw()->json() ?? [];
    }

    public function sendMessage(string $chatId, string $text, ?string $replyToMessageId = null): array
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        if ($replyToMessageId !== null && $replyToMessageId !== '') {
            $payload['reply_parameters'] = [
                'message_id' => (int) $replyToMessageId,
            ];
        }

        return $this->request()->post('/sendMessage', $payload)->throw()->json() ?? [];
    }

    public function setMessageReaction(string $chatId, string $messageId, string $emoji, bool $isBig = false): array
    {
        return $this->request()->post('/setMessageReaction', [
            'chat_id' => $chatId,
            'message_id' => (int) $messageId,
            'reaction' => [
                [
                    'type' => 'emoji',
                    'emoji' => $emoji,
                ],
            ],
            'is_big' => $isBig,
        ])->throw()->json() ?? [];
    }

    public function downloadFile(string $filePath): Response
    {
        $config = $this->config();

        return Http::baseUrl(rtrim((string) $config['base_url'], '/') . '/file/bot' . $config['bot_token'])
            ->timeout((int) ($config['timeout'] ?? 15))
            ->connectTimeout((int) ($config['connect_timeout'] ?? 5))
            ->get('/' . ltrim($filePath, '/'));
    }

    private function request(): PendingRequest
    {
        $config = $this->config();

        return Http::baseUrl(rtrim((string) $config['base_url'], '/') . '/bot' . $config['bot_token'])
            ->acceptJson()
            ->asJson()
            ->timeout((int) ($config['timeout'] ?? 15))
            ->connectTimeout((int) ($config['connect_timeout'] ?? 5));
    }

    private function config(): array
    {
        $config = config('services.telegram', []);
        $baseUrl = (string) ($config['base_url'] ?? '');
        $botToken = (string) ($config['bot_token'] ?? '');

        if ($baseUrl === '') {
            throw new \InvalidArgumentException('services.telegram.base_url is not configured.');
        }

        if ($botToken === '') {
            throw new \InvalidArgumentException('services.telegram.bot_token is not configured.');
        }

        return $config;
    }
}
