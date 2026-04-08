<?php

namespace App\Modules\MediaIntelligence\Http\Controllers;

use App\Modules\MediaIntelligence\Actions\UpsertMediaIntelligenceGlobalSettingsAction;
use App\Modules\MediaIntelligence\Http\Requests\UpsertMediaIntelligenceGlobalSettingsRequest;
use App\Modules\MediaIntelligence\Http\Resources\MediaIntelligenceGlobalSettingResource;
use App\Modules\MediaIntelligence\Models\MediaIntelligenceGlobalSetting;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaIntelligenceGlobalSettingsController extends BaseController
{
    public function show(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasAnyRole(['super-admin', 'platform-admin']) ?? false, 403);

        $settings = MediaIntelligenceGlobalSetting::query()->firstOrNew(
            ['id' => 1],
            MediaIntelligenceGlobalSetting::defaultAttributes(),
        );
        $settings->loadMissing('replyPromptPreset');

        return $this->success(new MediaIntelligenceGlobalSettingResource($settings));
    }

    public function update(
        UpsertMediaIntelligenceGlobalSettingsRequest $request,
        UpsertMediaIntelligenceGlobalSettingsAction $action,
    ): JsonResponse {
        $settings = $action->execute($request->validated());

        activity()
            ->performedOn($settings)
            ->causedBy($request->user())
            ->withProperties([
                'media_intelligence_global_settings_id' => $settings->id,
            ])
            ->log('Configuracao global de media intelligence atualizada');

        return $this->success(new MediaIntelligenceGlobalSettingResource($settings->loadMissing('replyPromptPreset')));
    }
}
