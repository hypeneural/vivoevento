<?php

namespace App\Modules\FaceSearch\Http\Controllers;

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Actions\DeleteEventFaceSearchCollectionAction;
use App\Modules\FaceSearch\Actions\QueueEventFaceSearchReconciliationAction;
use App\Modules\FaceSearch\Actions\QueueEventFaceSearchReindexAction;
use App\Modules\FaceSearch\Actions\RunEventFaceSearchHealthCheckAction;
use App\Shared\Http\BaseController;
use App\Shared\Support\EventAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventFaceSearchOperationsController extends BaseController
{
    public function health(
        Request $request,
        Event $event,
        EventAccessService $eventAccess,
        RunEventFaceSearchHealthCheckAction $action,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'media.moderate'), 403);

        return $this->success($action->execute($event));
    }

    public function reindex(
        Request $request,
        Event $event,
        EventAccessService $eventAccess,
        QueueEventFaceSearchReindexAction $action,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'media.moderate'), 403);

        return $this->success($action->execute($event));
    }

    public function reconcile(
        Request $request,
        Event $event,
        EventAccessService $eventAccess,
        QueueEventFaceSearchReconciliationAction $action,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'media.moderate'), 403);

        return $this->success($action->execute($event));
    }

    public function deleteCollection(
        Request $request,
        Event $event,
        EventAccessService $eventAccess,
        DeleteEventFaceSearchCollectionAction $action,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'media.moderate'), 403);

        return $this->success($action->execute($event));
    }
}
