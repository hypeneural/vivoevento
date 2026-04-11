<?php

namespace App\Modules\EventPeople\Http\Controllers;

use App\Modules\EventPeople\Actions\CreateEventPersonAction;
use App\Modules\EventPeople\Actions\UpdateEventPersonAction;
use App\Modules\EventPeople\Http\Requests\ListEventPeopleRequest;
use App\Modules\EventPeople\Http\Requests\StoreEventPersonRequest;
use App\Modules\EventPeople\Http\Requests\UpdateEventPersonRequest;
use App\Modules\EventPeople\Http\Resources\EventPersonResource;
use App\Modules\EventPeople\Models\EventPerson;
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

        $person = $action->execute($event, $request->user(), $request->validated())
            ->load(['mediaStats', 'representativeFaces.face', 'outgoingRelations.personA', 'outgoingRelations.personB', 'incomingRelations.personA', 'incomingRelations.personB']);

        return $this->created(new EventPersonResource($person));
    }

    public function index(
        ListEventPeopleRequest $request,
        Event $event,
        EventAccessService $eventAccess,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'media.view'), 403);

        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 36);

        $query = EventPerson::query()
            ->forEvent($event->id)
            ->with('mediaStats')
            ->orderByDesc('importance_rank')
            ->orderBy('display_name');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['side'])) {
            $query->where('side', $filters['side']);
        }

        if (! empty($filters['search'])) {
            $term = mb_strtolower($filters['search']);
            $query->whereRaw('LOWER(display_name) LIKE ?', ["%{$term}%"]);
        }

        return $this->paginated(EventPersonResource::collection($query->paginate($perPage)));
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

        $updated = $action->execute($person, $request->user(), $request->validated())
            ->load(['mediaStats', 'representativeFaces.face', 'outgoingRelations.personA', 'outgoingRelations.personB', 'incomingRelations.personA', 'incomingRelations.personB']);

        return $this->success(new EventPersonResource($updated));
    }

    public function show(
        Request $request,
        Event $event,
        EventPerson $person,
        EventAccessService $eventAccess,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'media.view'), 403);
        abort_unless((int) $person->event_id === (int) $event->id, 404);

        $person->load([
            'mediaStats',
            'assignments.face.media',
            'representativeFaces.face',
            'outgoingRelations.personA',
            'outgoingRelations.personB',
            'incomingRelations.personA',
            'incomingRelations.personB',
        ]);

        return $this->success(new EventPersonResource($person));
    }
}
