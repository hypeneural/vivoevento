<?php

namespace App\Modules\FaceSearch\Http\Controllers;

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Actions\UpsertEventFaceSearchSettingsAction;
use App\Modules\FaceSearch\Http\Requests\UpsertEventFaceSearchSettingsRequest;
use App\Modules\FaceSearch\Http\Resources\EventFaceSearchSettingResource;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Shared\Http\BaseController;
use App\Shared\Support\EventAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventFaceSearchSettingsController extends BaseController
{
    public function show(
        Request $request,
        Event $event,
        EventAccessService $eventAccess,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'media.moderate'), 403);

        $settings = $event->faceSearchSettings()->firstOrNew(
            ['event_id' => $event->id],
            EventFaceSearchSetting::defaultAttributes(),
        );

        return $this->success(new EventFaceSearchSettingResource($settings));
    }

    public function update(
        UpsertEventFaceSearchSettingsRequest $request,
        Event $event,
        EventAccessService $eventAccess,
        UpsertEventFaceSearchSettingsAction $action,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'media.moderate'), 403);

        $settings = $action->execute($event, $request->validated());

        activity()
            ->performedOn($event)
            ->causedBy($request->user())
            ->withProperties([
                'event_id' => $event->id,
                'face_search_settings_id' => $settings->id,
                'enabled' => (bool) $settings->enabled,
                'allow_public_selfie_search' => (bool) $settings->allow_public_selfie_search,
                'provider_key' => $settings->provider_key,
                'vector_store_key' => $settings->vector_store_key,
            ])
            ->log('Configuracao de FaceSearch atualizada');

        return $this->success(new EventFaceSearchSettingResource($settings->refresh()));
    }
}
