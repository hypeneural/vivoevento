<?php

namespace App\Modules\EventPeople\Http\Controllers;

use App\Modules\EventPeople\Actions\DeleteEventPersonRelationAction;
use App\Modules\EventPeople\Actions\UpsertEventPersonRelationAction;
use App\Modules\EventPeople\Http\Requests\StoreEventPersonRelationRequest;
use App\Modules\EventPeople\Http\Requests\UpdateEventPersonRelationRequest;
use App\Modules\EventPeople\Http\Resources\EventPersonRelationResource;
use App\Modules\EventPeople\Models\EventPersonRelation;
use App\Modules\Events\Models\Event;
use App\Shared\Http\BaseController;
use App\Shared\Support\EventAccessService;
use Illuminate\Http\JsonResponse;

class EventPersonRelationsController extends BaseController
{
    public function store(
        StoreEventPersonRelationRequest $request,
        Event $event,
        EventAccessService $eventAccess,
        UpsertEventPersonRelationAction $action,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'events.update'), 403);

        $relation = $action->execute($event, $request->user(), $request->validated());

        return $this->created(new EventPersonRelationResource($relation));
    }

    public function update(
        UpdateEventPersonRelationRequest $request,
        Event $event,
        EventPersonRelation $relation,
        EventAccessService $eventAccess,
        UpsertEventPersonRelationAction $action,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'events.update'), 403);
        abort_unless((int) $relation->event_id === (int) $event->id, 404);

        $payload = array_merge([
            'person_a_id' => $request->validated('person_a_id', $relation->person_a_id),
            'person_b_id' => $request->validated('person_b_id', $relation->person_b_id),
            'relation_type' => $request->validated('relation_type', $relation->relation_type?->value ?? $relation->relation_type),
        ], $request->validated());

        $updated = $action->execute($event, $request->user(), $payload, $relation);

        return $this->success(new EventPersonRelationResource($updated));
    }

    public function destroy(
        \Illuminate\Http\Request $request,
        Event $event,
        EventPersonRelation $relation,
        EventAccessService $eventAccess,
        DeleteEventPersonRelationAction $action,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'events.update'), 403);
        abort_unless((int) $relation->event_id === (int) $event->id, 404);

        $action->execute($relation);

        return $this->noContent();
    }
}
