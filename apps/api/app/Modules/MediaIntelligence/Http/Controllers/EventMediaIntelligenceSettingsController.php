<?php

namespace App\Modules\MediaIntelligence\Http\Controllers;

use App\Modules\Events\Models\Event;
use App\Modules\MediaIntelligence\Actions\UpsertEventMediaIntelligenceSettingsAction;
use App\Modules\MediaIntelligence\Http\Requests\UpsertEventMediaIntelligenceSettingsRequest;
use App\Modules\MediaIntelligence\Http\Resources\EventMediaIntelligenceSettingResource;
use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;
use App\Shared\Http\BaseController;
use App\Shared\Support\EventAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventMediaIntelligenceSettingsController extends BaseController
{
    public function show(
        Request $request,
        Event $event,
        EventAccessService $eventAccess,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'media.moderate'), 403);

        $settings = $event->mediaIntelligenceSettings()->firstOrNew(
            ['event_id' => $event->id],
            EventMediaIntelligenceSetting::defaultAttributes(),
        );
        $settings->loadMissing('replyPromptPreset');

        return $this->success(new EventMediaIntelligenceSettingResource($settings));
    }

    public function update(
        UpsertEventMediaIntelligenceSettingsRequest $request,
        Event $event,
        EventAccessService $eventAccess,
        UpsertEventMediaIntelligenceSettingsAction $action,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'media.moderate'), 403);

        $settings = $action->execute($event, $request->validated());

        activity()
            ->performedOn($event)
            ->causedBy($request->user())
            ->withProperties([
                'event_id' => $event->id,
                'media_intelligence_settings_id' => $settings->id,
                'provider_key' => $settings->provider_key,
                'model_key' => $settings->model_key,
                'enabled' => (bool) $settings->enabled,
                'mode' => $settings->mode,
            ])
            ->log('Configuracao de media intelligence atualizada');

        return $this->success(new EventMediaIntelligenceSettingResource($settings->refresh()->loadMissing('replyPromptPreset')));
    }
}
