<?php

namespace App\Modules\Telegram\Http\Controllers;

use App\Modules\Events\Models\Event;
use App\Modules\Telegram\Services\TelegramOperationalStatusService;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;

class EventTelegramOperationalStatusController extends BaseController
{
    public function show(Event $event, TelegramOperationalStatusService $status): JsonResponse
    {
        $this->authorize('view', $event);

        return $this->success($status->build($event));
    }
}
