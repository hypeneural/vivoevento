<?php

namespace App\Modules\EventOperations\Http\Controllers;

use App\Modules\EventOperations\Actions\BuildEventOperationsRoomAction;
use App\Modules\EventOperations\Actions\BuildEventOperationsTimelineAction;
use App\Modules\EventOperations\Http\Requests\ListEventOperationsTimelineRequest;
use App\Modules\EventOperations\Http\Requests\ShowEventOperationsRoomRequest;
use App\Modules\EventOperations\Http\Resources\EventOperationsRoomResource;
use App\Modules\EventOperations\Http\Resources\EventOperationsTimelineResource;
use App\Modules\Events\Models\Event;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;

class EventOperationsController extends BaseController
{
    public function room(
        ShowEventOperationsRoomRequest $request,
        Event $event,
        BuildEventOperationsRoomAction $action,
    ): JsonResponse {
        return $this->success(new EventOperationsRoomResource(
            $action->execute($event)
        ));
    }

    public function timeline(
        ListEventOperationsTimelineRequest $request,
        Event $event,
        BuildEventOperationsTimelineAction $action,
    ): JsonResponse {
        return $this->success(new EventOperationsTimelineResource(
            $action->execute($event, $request->validated())
        ));
    }
}
