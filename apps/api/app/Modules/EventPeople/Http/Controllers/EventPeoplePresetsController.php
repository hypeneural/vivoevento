<?php

namespace App\Modules\EventPeople\Http\Controllers;

use App\Modules\EventPeople\Services\EventPeoplePresetCatalog;
use App\Modules\Events\Models\Event;
use App\Shared\Http\BaseController;
use App\Shared\Support\EventAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventPeoplePresetsController extends BaseController
{
    public function show(
        Request $request,
        Event $event,
        EventAccessService $eventAccess,
        EventPeoplePresetCatalog $catalog,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'media.view'), 403);

        return $this->success([
            'event_type' => $event->event_type?->value ?? $event->event_type,
            ...$catalog->forEventType($event->event_type?->value ?? $event->event_type),
        ]);
    }
}
