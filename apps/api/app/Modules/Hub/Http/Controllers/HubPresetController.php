<?php

namespace App\Modules\Hub\Http\Controllers;

use App\Modules\Events\Models\Event;
use App\Modules\Hub\Actions\CreateHubPresetAction;
use App\Modules\Hub\Http\Requests\ListHubPresetsRequest;
use App\Modules\Hub\Http\Requests\StoreHubPresetRequest;
use App\Modules\Hub\Http\Resources\HubPresetResource;
use App\Modules\Hub\Queries\ListHubPresetsQuery;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;

class HubPresetController extends BaseController
{
    public function index(
        ListHubPresetsRequest $request,
        ListHubPresetsQuery $query,
    ): JsonResponse {
        $organizationId = $request->user()?->currentOrganization()?->id;

        abort_unless($organizationId, 422, 'Nenhuma organizacao ativa encontrada.');

        return $this->success(
            HubPresetResource::collection($query->execute($organizationId))->resolve(),
        );
    }

    public function store(
        StoreHubPresetRequest $request,
        CreateHubPresetAction $action,
    ): JsonResponse {
        $organizationId = $request->user()?->currentOrganization()?->id;

        abort_unless($organizationId, 422, 'Nenhuma organizacao ativa encontrada.');

        $validated = $request->validated();
        $sourceEvent = null;

        if (($validated['event_id'] ?? null) !== null) {
            $sourceEvent = Event::query()
                ->where('organization_id', $organizationId)
                ->find($validated['event_id']);
        }

        $preset = $action->execute(
            $request->user(),
            $organizationId,
            $validated,
            $sourceEvent,
        );

        activity()
            ->performedOn($sourceEvent)
            ->causedBy($request->user())
            ->withProperties([
                'organization_id' => $organizationId,
                'hub_preset_id' => $preset->id,
                'hub_preset_name' => $preset->name,
            ])
            ->log('Modelo do hub salvo');

        return $this->created((new HubPresetResource($preset->loadMissing(['creator:id,name', 'sourceEvent:id,title,slug'])))->resolve());
    }
}
