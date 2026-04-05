<?php

namespace App\Modules\Events\Http\Controllers;

use App\Modules\Events\Actions\ArchiveEventAction;
use App\Modules\Events\Actions\PublishEventAction;
use App\Modules\Events\Http\Resources\EventResource;
use App\Modules\Events\Models\Event;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;

class EventStatusController extends BaseController
{
    public function publish(Event $event, PublishEventAction $action): JsonResponse
    {
        $event = $action->execute($event);

        return $this->success(new EventResource($event));
    }

    public function archive(Event $event, ArchiveEventAction $action): JsonResponse
    {
        $event = $action->execute($event);

        return $this->success(new EventResource($event));
    }
}
