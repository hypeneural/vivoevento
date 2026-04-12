<?php

namespace App\Modules\EventPeople\Http\Controllers;

use App\Modules\EventPeople\Actions\CreateEventPersonAction;
use App\Modules\EventPeople\Actions\UpdateEventPersonAction;
use App\Modules\EventPeople\Http\Requests\ListEventPeopleRequest;
use App\Modules\EventPeople\Http\Resources\EventPeopleGraphResource;
use App\Modules\EventPeople\Http\Requests\StoreEventPersonRequest;
use App\Modules\EventPeople\Http\Requests\UpdateEventPersonRequest;
use App\Modules\EventPeople\Http\Resources\EventPersonResource;
use App\Modules\EventPeople\Models\EventPerson;
use App\Modules\EventPeople\Queries\BuildEventPeopleGraphQuery;
use App\Modules\EventPeople\Queries\ListEventPeopleQuery;
use App\Modules\EventPeople\Services\EventPeopleOperationalMetricsService;
use App\Modules\Events\Models\Event;
use App\Shared\Http\BaseController;
use App\Shared\Support\EventAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventPeopleController extends BaseController
{
    public function store(
        StoreEventPersonRequest $request,
        Event $event,
        EventAccessService $eventAccess,
        CreateEventPersonAction $action,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'events.update'), 403);

        $person = $this->loadPerson(
            $action->execute($event, $request->user(), $request->validated())
        );

        return $this->created(new EventPersonResource($person));
    }

    public function index(
        ListEventPeopleRequest $request,
        Event $event,
        EventAccessService $eventAccess,
        ListEventPeopleQuery $query,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'media.view'), 403);

        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 36);

        return $this->paginated(EventPersonResource::collection($query->paginate($event, $filters, $perPage)));
    }

    public function update(
        UpdateEventPersonRequest $request,
        Event $event,
        EventPerson $person,
        EventAccessService $eventAccess,
        UpdateEventPersonAction $action,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'events.update'), 403);
        abort_unless((int) $person->event_id === (int) $event->id, 404);

        $updated = $this->loadPerson(
            $action->execute($person, $request->user(), $request->validated())
        );

        return $this->success(new EventPersonResource($updated));
    }

    public function graph(
        ListEventPeopleRequest $request,
        Event $event,
        EventAccessService $eventAccess,
        BuildEventPeopleGraphQuery $query,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'media.view'), 403);

        return $this->success(new EventPeopleGraphResource(
            $query->build($event, $request->validated())
        ));
    }

    public function show(
        Request $request,
        Event $event,
        EventPerson $person,
        EventAccessService $eventAccess,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'media.view'), 403);
        abort_unless((int) $person->event_id === (int) $event->id, 404);

        $person = $this->loadPerson($person, [
            'assignments.face.media',
        ]);

        return $this->success(new EventPersonResource($person));
    }

    public function operationalStatus(
        Request $request,
        Event $event,
        EventAccessService $eventAccess,
        EventPeopleOperationalMetricsService $metrics,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'media.view'), 403);

        return $this->success($metrics->snapshot($event->id));
    }

    /**
     * @param  array<int, string>  $extraRelations
     */
    private function loadPerson(EventPerson $person, array $extraRelations = []): EventPerson
    {
        return $person->load(array_merge([
            'mediaStats',
            'primaryReferencePhoto.face',
            'primaryReferencePhoto.uploadMedia',
            'referencePhotos.face',
            'referencePhotos.uploadMedia',
            'representativeFaces.face',
            'outgoingRelations.personA',
            'outgoingRelations.personB',
            'incomingRelations.personA',
            'incomingRelations.personB',
        ], $extraRelations));
    }
}
