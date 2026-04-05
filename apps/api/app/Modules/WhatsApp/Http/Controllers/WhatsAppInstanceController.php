<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Modules\WhatsApp\Actions\CreateWhatsAppInstanceAction;
use App\Modules\WhatsApp\Actions\SetDefaultWhatsAppInstanceAction;
use App\Modules\WhatsApp\Actions\TestWhatsAppInstanceConnectionAction;
use App\Modules\WhatsApp\Actions\UpdateWhatsAppInstanceAction;
use App\Modules\WhatsApp\Http\Requests\ListInstancesRequest;
use App\Modules\WhatsApp\Http\Requests\StoreInstanceRequest;
use App\Modules\WhatsApp\Http\Requests\UpdateInstanceRequest;
use App\Modules\WhatsApp\Http\Resources\WhatsAppInstanceResource;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use App\Modules\WhatsApp\Queries\ListWhatsAppInstancesQuery;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppInstanceController extends BaseController
{
    public function index(ListInstancesRequest $request): JsonResponse
    {
        $this->authorize('viewAny', WhatsAppInstance::class);

        $validated = $request->validated();

        $query = new ListWhatsAppInstancesQuery(
            organizationId: $this->resolveOrganizationId($request->user(), $validated['organization_id'] ?? null),
            search: $validated['search'] ?? null,
            providerKey: $validated['provider_key'] ?? null,
            status: $validated['status'] ?? null,
            isDefault: array_key_exists('is_default', $validated) ? (bool) $validated['is_default'] : null,
            isActive: array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : null,
        );

        $instances = $query->query()->paginate($validated['per_page'] ?? 15);

        return $this->paginated(WhatsAppInstanceResource::collection($instances));
    }

    public function store(StoreInstanceRequest $request, CreateWhatsAppInstanceAction $action): JsonResponse
    {
        $this->authorize('create', WhatsAppInstance::class);

        $instance = $action->execute($request->validated(), $request->user());

        return $this->created(new WhatsAppInstanceResource($instance));
    }

    public function show(WhatsAppInstance $instance): JsonResponse
    {
        $this->authorize('view', $instance);

        return $this->success(new WhatsAppInstanceResource($instance->load('provider')));
    }

    public function update(UpdateInstanceRequest $request, WhatsAppInstance $instance, UpdateWhatsAppInstanceAction $action): JsonResponse
    {
        $this->authorize('update', $instance);

        $instance = $action->execute($instance, $request->validated(), $request->user());

        return $this->success(new WhatsAppInstanceResource($instance));
    }

    public function destroy(WhatsAppInstance $instance): JsonResponse
    {
        $this->authorize('delete', $instance);

        if ($instance->is_default) {
            return $this->error('Remova a definicao de padrao antes de excluir esta instancia.', 422);
        }

        $instance->delete();

        return $this->noContent();
    }

    public function testConnection(WhatsAppInstance $instance, TestWhatsAppInstanceConnectionAction $action): JsonResponse
    {
        $this->authorize('update', $instance);

        $result = $action->execute($instance);

        return $this->success([
            'success' => $result['success'],
            'connected' => $result['connected'],
            'status' => $result['status'],
            'message' => $result['message'],
            'error' => $result['error'],
            'checked_at' => $result['checked_at'],
            'instance' => new WhatsAppInstanceResource($result['instance']),
        ]);
    }

    public function setDefault(WhatsAppInstance $instance, SetDefaultWhatsAppInstanceAction $action): JsonResponse
    {
        $this->authorize('update', $instance);

        $instance = $action->execute($instance);

        return $this->success(new WhatsAppInstanceResource($instance));
    }

    private function resolveOrganizationId($user, ?int $organizationId): int
    {
        if ($user->hasAnyRole(['super-admin', 'platform-admin']) && $organizationId) {
            return $organizationId;
        }

        return (int) ($user->currentOrganization()?->id ?? $user->current_organization_id ?? 0);
    }
}
