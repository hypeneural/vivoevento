<?php

namespace App\Modules\Events\Http\Controllers;

use App\Modules\Events\Actions\BuildEventJourneyProjectionAction;
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
}
