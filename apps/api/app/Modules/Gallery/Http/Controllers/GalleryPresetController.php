<?php

namespace App\Modules\Gallery\Http\Controllers;

use App\Modules\Events\Models\Event;
use App\Modules\Gallery\Actions\CreateGalleryPresetAction;
use App\Modules\Gallery\Http\Requests\ListGalleryPresetsRequest;
use App\Modules\Gallery\Http\Requests\StoreGalleryPresetRequest;
use App\Modules\Gallery\Http\Resources\GalleryPresetResource;
use App\Modules\Gallery\Models\EventGallerySetting;
use App\Modules\Gallery\Queries\ListGalleryPresetsQuery;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;

class GalleryPresetController extends BaseController
{
    public function index(
        ListGalleryPresetsRequest $request,
        ListGalleryPresetsQuery $query,
    ): JsonResponse {
        $organizationId = $request->user()?->currentOrganization()?->id;

        abort_unless($organizationId, 422, 'Nenhuma organizacao ativa encontrada.');

        return $this->success(
            GalleryPresetResource::collection($query->execute($organizationId))->resolve(),
        );
    }

    public function store(
        StoreGalleryPresetRequest $request,
        CreateGalleryPresetAction $action,
    ): JsonResponse {
        $organizationId = $request->user()?->currentOrganization()?->id;

        abort_unless($organizationId, 422, 'Nenhuma organizacao ativa encontrada.');

        $validated = $request->validated();
        $sourceEvent = Event::query()
            ->where('organization_id', $organizationId)
            ->findOrFail($validated['event_id']);

        $sourceSettings = EventGallerySetting::query()
            ->where('event_id', $sourceEvent->id)
            ->first();

        $preset = $action->execute(
            $request->user(),
            $organizationId,
            $validated,
            $sourceEvent,
            $sourceSettings,
        );

        return $this->created(
            (new GalleryPresetResource($preset->loadMissing(['creator:id,name', 'sourceEvent:id,title,slug'])))->resolve(),
        );
    }
}
