<?php

namespace App\Modules\ContentModeration\Http\Controllers;

use App\Modules\ContentModeration\Actions\UpsertContentModerationGlobalSettingsAction;
use App\Modules\ContentModeration\Http\Requests\UpsertContentModerationGlobalSettingsRequest;
use App\Modules\ContentModeration\Http\Resources\ContentModerationGlobalSettingResource;
use App\Modules\ContentModeration\Services\ContentModerationSettingsResolver;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentModerationGlobalSettingsController extends BaseController
{
    public function show(
        Request $request,
        ContentModerationSettingsResolver $resolver,
    ): JsonResponse {
        abort_unless($request->user()?->hasAnyRole(['super-admin', 'platform-admin']) ?? false, 403);

        return $this->success(new ContentModerationGlobalSettingResource($resolver->resolveGlobal()));
    }

    public function update(
        UpsertContentModerationGlobalSettingsRequest $request,
        UpsertContentModerationGlobalSettingsAction $action,
    ): JsonResponse {
        $settings = $action->execute($request->validated());

        activity()
            ->performedOn($settings)
            ->causedBy($request->user())
            ->withProperties([
                'content_moderation_global_settings_id' => $settings->id,
            ])
            ->log('Configuracao global de safety atualizada');

        return $this->success(new ContentModerationGlobalSettingResource($settings));
    }
}
