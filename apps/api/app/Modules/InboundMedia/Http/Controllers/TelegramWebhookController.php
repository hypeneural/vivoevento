<?php

namespace App\Modules\InboundMedia\Http\Controllers;

use App\Modules\InboundMedia\Jobs\ProcessInboundWebhookJob;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends BaseController
{
    public function handle(Request $request): JsonResponse
    {
        ProcessInboundWebhookJob::dispatch('telegram', $request->all());

        return $this->success(message: 'Webhook received');
    }
}
