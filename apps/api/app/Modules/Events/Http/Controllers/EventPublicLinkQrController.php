<?php

namespace App\Modules\Events\Http\Controllers;

use App\Modules\Events\Actions\GetEventPublicLinkQrConfigAction;
use App\Modules\Events\Actions\ResetEventPublicLinkQrConfigAction;
use App\Modules\Events\Actions\UpsertEventPublicLinkQrConfigAction;
use App\Modules\Events\Http\Requests\UpsertEventPublicLinkQrConfigRequest;
use App\Modules\Events\Http\Resources\EventPublicLinkQrResource;
use App\Modules\Events\Models\Event;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;

class EventPublicLinkQrController extends BaseController
{
    public function index(Event $event, GetEventPublicLinkQrConfigAction $action): JsonResponse
    {
        $this->authorize('view', $event);

        $payload = array_map(
            fn (array $item) => (new EventPublicLinkQrResource($item))->resolve(),
            $action->list($event),
        );

        return $this->success($payload);
    }

    public function show(
        Event $event,
        string $linkKey,
        GetEventPublicLinkQrConfigAction $action,
    ): JsonResponse {
        $this->authorize('view', $event);

        return $this->success(
            new EventPublicLinkQrResource($action->execute($event, $linkKey))
        );
    }

    public function update(
        UpsertEventPublicLinkQrConfigRequest $request,
        Event $event,
        string $linkKey,
        UpsertEventPublicLinkQrConfigAction $upsert,
        GetEventPublicLinkQrConfigAction $get,
    ): JsonResponse {
        $this->authorize('update', $event);

        $upsert->execute(
            event: $event,
            linkKey: $linkKey,
            config: $request->validated('config'),
            userId: $request->user()?->id,
        );

        return $this->success(
            new EventPublicLinkQrResource($get->execute($event, $linkKey))
        );
    }

    public function reset(
        Event $event,
        string $linkKey,
        ResetEventPublicLinkQrConfigAction $reset,
        GetEventPublicLinkQrConfigAction $get,
    ): JsonResponse {
        $this->authorize('update', $event);

        $reset->execute($event, $linkKey);

        return $this->success(
            new EventPublicLinkQrResource($get->execute($event, $linkKey))
        );
    }
}
