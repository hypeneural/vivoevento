<?php

namespace App\Modules\ContentModeration\Http\Controllers;

use App\Modules\ContentModeration\Actions\UpsertEventContentModerationSettingsAction;
use App\Modules\ContentModeration\Http\Requests\UpsertEventContentModerationSettingsRequest;
use App\Modules\ContentModeration\Http\Resources\EventContentModerationSettingResource;
use App\Modules\ContentModeration\Models\EventContentModerationSetting;
use App\Modules\Events\Models\Event;
use App\Shared\Http\BaseController;
use App\Shared\Support\EventAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventContentModerationSettingsController extends BaseController
{
    public function show(
        Request $request,
        Event $event,
        EventAccessService $eventAccess,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'media.moderate'), 403);

        $settings = $event->contentModerationSettings()->firstOrNew(
            ['event_id' => $event->id],
            EventContentModerationSetting::defaultAttributes(),
        );

        return $this->success(new EventContentModerationSettingResource($settings));
    }

    public function update(
        UpsertEventContentModerationSettingsRequest $request,
        Event $event,
        EventAccessService $eventAccess,
        UpsertEventContentModerationSettingsAction $action,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'media.moderate'), 403);

        $settings = $action->execute($event, $request->validated());

        activity()
            ->performedOn($event)
            ->causedBy($request->user())
            ->withProperties([
                'event_id' => $event->id,
                'content_moderation_settings_id' => $settings->id,
                'provider_key' => $settings->provider_key,
                'enabled' => (bool) $settings->enabled,
            ])
            ->log('Configuracao de safety atualizada');

        return $this->success(new EventContentModerationSettingResource($settings->refresh()));
    }
}
