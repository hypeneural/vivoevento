<?php

namespace App\Modules\Events\Http\Controllers;

use App\Modules\Events\Actions\UploadEventBrandingAssetAction;
use App\Modules\Events\Http\Requests\UploadEventBrandingAssetRequest;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Http\Resources\EventResource;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventBrandingController extends BaseController
{
    public function storeAsset(
        UploadEventBrandingAssetRequest $request,
        UploadEventBrandingAssetAction $action,
    ): JsonResponse {
        $payload = $action->execute(
            user: $request->user(),
            file: $request->file('file'),
            kind: $request->validated('kind'),
            previousPath: $request->validated('previous_path'),
        );

        return $this->success($payload, 201);
    }

    public function update(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'cover_image_path' => ['nullable', 'string', 'max:255'],
            'logo_path' => ['nullable', 'string', 'max:255'],
            'primary_color' => ['nullable', 'string', 'max:20'],
            'secondary_color' => ['nullable', 'string', 'max:20'],
        ]);

        $event->update($validated);

        return $this->success(new EventResource($event->fresh()));
    }
}
