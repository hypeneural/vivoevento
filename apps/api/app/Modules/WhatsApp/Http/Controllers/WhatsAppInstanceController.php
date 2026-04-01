<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Modules\WhatsApp\Enums\WhatsAppProviderKey;
use App\Modules\WhatsApp\Http\Requests\StoreInstanceRequest;
use App\Modules\WhatsApp\Http\Requests\UpdateInstanceRequest;
use App\Modules\WhatsApp\Http\Resources\WhatsAppInstanceResource;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use App\Modules\WhatsApp\Models\WhatsAppProvider;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WhatsAppInstanceController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $instances = WhatsAppInstance::forOrganization($request->user()->current_organization_id)
            ->with('provider')
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return $this->paginated(WhatsAppInstanceResource::collection($instances));
    }

    public function store(StoreInstanceRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $provider = WhatsAppProvider::where('key', $validated['provider_key'])->firstOrFail();

        $instance = WhatsAppInstance::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $request->user()->current_organization_id,
            'provider_id' => $provider->id,
            'provider_key' => $validated['provider_key'],
            'name' => $validated['name'],
            'external_instance_id' => $validated['external_instance_id'],
            'provider_token' => $validated['provider_token'],
            'provider_client_token' => $validated['provider_client_token'],
            'webhook_secret' => $validated['webhook_secret'] ?? null,
            'settings_json' => $validated['settings'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return $this->created(new WhatsAppInstanceResource($instance->load('provider')));
    }

    public function show(WhatsAppInstance $instance): JsonResponse
    {
        return $this->success(new WhatsAppInstanceResource($instance->load('provider')));
    }

    public function update(UpdateInstanceRequest $request, WhatsAppInstance $instance): JsonResponse
    {
        $instance->update($request->validated());

        return $this->success(new WhatsAppInstanceResource($instance->fresh()->load('provider')));
    }

    public function destroy(WhatsAppInstance $instance): JsonResponse
    {
        $instance->delete();

        return $this->noContent();
    }
}
