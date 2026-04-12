<?php

namespace App\Modules\EventPeople\Http\Controllers;

use App\Modules\EventPeople\Actions\BuildEventRelationalCollectionsAction;
use App\Modules\EventPeople\Http\Resources\EventRelationalCollectionResource;
use App\Modules\EventPeople\Queries\ListEventRelationalCollectionsQuery;
use App\Modules\Events\Models\Event;
use App\Shared\Http\BaseController;
use App\Shared\Support\EventAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventPeopleRelationalCollectionsController extends BaseController
{
    public function index(
        Request $request,
        Event $event,
        EventAccessService $eventAccess,
        ListEventRelationalCollectionsQuery $query,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'media.view'), 403);

        $collections = $query->get($event);

        return $this->success([
            'summary' => $query->summarize($collections),
            'collections' => EventRelationalCollectionResource::collection($collections),
        ]);
    }

    public function refresh(
        Request $request,
        Event $event,
        EventAccessService $eventAccess,
        BuildEventRelationalCollectionsAction $action,
        ListEventRelationalCollectionsQuery $query,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'events.update'), 403);

        $collections = $action->execute($event, $request->user());

        return $this->success([
            'summary' => $query->summarize($collections),
            'collections' => EventRelationalCollectionResource::collection($collections),
        ]);
    }
}
