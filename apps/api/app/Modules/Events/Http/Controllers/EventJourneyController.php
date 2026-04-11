<?php

namespace App\Modules\Events\Http\Controllers;

use App\Modules\Events\Actions\BuildEventJourneyProjectionAction;
use App\Modules\Events\Actions\UpdateEventJourneyAction;
use App\Modules\Events\Http\Requests\UpdateEventJourneyRequest;
use App\Modules\Events\Http\Resources\EventJourneyResource;
use App\Modules\Events\Models\Event;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;

class EventJourneyController extends BaseController
{
    public function show(Event $event, BuildEventJourneyProjectionAction $action): JsonResponse
    {
        $this->authorize('view', $event);

        return $this->success(
            new EventJourneyResource(
                $action->execute($event)
            )
        );
    }

    public function update(
        UpdateEventJourneyRequest $request,
        Event $event,
        UpdateEventJourneyAction $action,
    ): JsonResponse {
        $this->authorize('update', $event);

        $projection = $action->execute($event, $request->validated());

        activity()
            ->performedOn($event)
            ->causedBy($request->user())
            ->withProperties([
                'event_id' => $event->id,
                'moderation_mode' => data_get($request->validated(), 'moderation_mode'),
                'modules' => data_get($request->validated(), 'modules'),
            ])
            ->log('Jornada de midia atualizada');

        return $this->success(new EventJourneyResource($projection));
    }
}
