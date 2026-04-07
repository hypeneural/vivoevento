<?php

namespace App\Modules\Telegram\Http\Controllers;

use App\Modules\Telegram\Actions\HandleTelegramPrivateWebhookAction;
use App\Modules\Telegram\Support\TelegramWebhookSecretValidator;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends BaseController
{
    public function __construct(
        private readonly TelegramWebhookSecretValidator $secretValidator,
        private readonly HandleTelegramPrivateWebhookAction $handleTelegramPrivateWebhook,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $providedSecret = $request->header('X-Telegram-Bot-Api-Secret-Token');

        if (! $this->secretValidator->isValid($providedSecret)) {
            return $this->error('Invalid Telegram webhook secret.', 403);
        }

        $this->handleTelegramPrivateWebhook->execute($request->all());

        return $this->success(message: 'Webhook received');
    }
}
