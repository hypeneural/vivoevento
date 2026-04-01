<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Modules\WhatsApp\Http\Requests\BindGroupRequest;
use App\Modules\WhatsApp\Http\Resources\WhatsAppGroupBindingResource;
use App\Modules\WhatsApp\Models\WhatsAppGroupBinding;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppGroupBindingController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $bindings = WhatsAppGroupBinding::forOrganization($request->user()->current_organization_id)
            ->with('instance', 'event')
            ->when($request->input('event_id'), fn ($q, $v) => $q->forEvent($v))
            ->when($request->input('instance_id'), fn ($q, $v) => $q->where('instance_id', $v))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return $this->paginated(WhatsAppGroupBindingResource::collection($bindings));
    }

    public function bind(BindGroupRequest $request, string $groupId): JsonResponse
    {
        $validated = $request->validated();

        $binding = WhatsAppGroupBinding::updateOrCreate(
            [
                'instance_id' => $validated['instance_id'],
                'group_external_id' => $groupId,
                'binding_type' => $validated['binding_type'],
            ],
            [
                'organization_id' => $request->user()->current_organization_id,
                'event_id' => $validated['event_id'] ?? null,
                'group_name' => $validated['group_name'] ?? null,
                'is_active' => true,
                'metadata_json' => $validated['metadata'] ?? null,
            ]
        );

        return $this->created(new WhatsAppGroupBindingResource($binding->load('instance', 'event')));
    }

    public function update(Request $request, string $groupId): JsonResponse
    {
        $binding = WhatsAppGroupBinding::where('group_external_id', $groupId)
            ->where('instance_id', $request->input('instance_id'))
            ->firstOrFail();

        $binding->update($request->only([
            'group_name', 'binding_type', 'is_active', 'event_id', 'metadata_json',
        ]));

        return $this->success(new WhatsAppGroupBindingResource($binding->fresh()->load('instance', 'event')));
    }

    public function unbind(Request $request, string $groupId): JsonResponse
    {
        $binding = WhatsAppGroupBinding::where('group_external_id', $groupId)
            ->where('instance_id', $request->input('instance_id'))
            ->firstOrFail();

        $binding->update(['is_active' => false]);

        return $this->noContent();
    }
}
