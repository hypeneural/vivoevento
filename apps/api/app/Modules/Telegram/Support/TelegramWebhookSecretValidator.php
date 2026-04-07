<?php

namespace App\Modules\Telegram\Support;

class TelegramWebhookSecretValidator
{
    public function isValid(?string $providedSecret): bool
    {
        $configuredSecret = (string) (config('services.telegram.webhook_secret_token') ?? '');

        if ($configuredSecret === '') {
            return false;
        }

        if (! is_string($providedSecret) || trim($providedSecret) === '') {
            return false;
        }

        return hash_equals($configuredSecret, $providedSecret);
    }
}
