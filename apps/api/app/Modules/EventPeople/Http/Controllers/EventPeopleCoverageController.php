<?php

namespace App\Modules\EventPeople\Http\Controllers;

use App\Modules\EventPeople\Actions\RefreshEventCoverageAction;
use App\Modules\EventPeople\Queries\ListEventPeopleCoverageQuery;
use App\Modules\Events\Models\Event;
use App\Shared\Http\BaseController;
use App\Shared\Support\EventAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventPeopleCoverageController extends BaseController
{
    public function index(
        Request $request,
        Event $event,
        EventAccessService $eventAccess,
        ListEventPeopleCoverageQuery $query,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'media.view'), 403);

        return $this->success($query->get($event));
    }

    public function refresh(
        Request $request,
        Event $event,
        EventAccessService $eventAccess,
        RefreshEventCoverageAction $action,
        ListEventPeopleCoverageQuery $query,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'events.update'), 403);

        $action->execute($event, $request->user());

        return $this->success($query->get($event));
    }
}
