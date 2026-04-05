<?php

namespace App\Modules\Clients\Http\Controllers;

use App\Modules\Clients\Actions\CreateClientAction;
use App\Modules\Clients\Actions\UpdateClientAction;
use App\Modules\Clients\Http\Requests\ListClientsRequest;
use App\Modules\Clients\Http\Requests\StoreClientRequest;
use App\Modules\Clients\Http\Requests\UpdateClientRequest;
use App\Modules\Clients\Http\Resources\ClientResource;
use App\Modules\Clients\Models\Client;
use App\Modules\Clients\Queries\ListClientsQuery;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;

class ClientController extends BaseController
{
    public function index(ListClientsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Client::class);

        $validated = $request->validated();
        $user = $request->user();

        $query = new ListClientsQuery(
            organizationId: $this->resolveOrganizationId($user, $validated['organization_id'] ?? null),
            search: $validated['search'] ?? null,
            type: $validated['type'] ?? null,
            planCode: $validated['plan_code'] ?? null,
            hasEvents: array_key_exists('has_events', $validated) ? (bool) $validated['has_events'] : null,
            sortBy: $validated['sort_by'] ?? 'created_at',
            sortDirection: $validated['sort_direction'] ?? 'desc',
        );

        $clients = $query->query()->paginate($validated['per_page'] ?? 15);

        return $this->paginated(ClientResource::collection($clients));
    }

    public function store(StoreClientRequest $request, CreateClientAction $action): JsonResponse
    {
        $this->authorize('create', Client::class);

        $client = $action->execute($request->validated(), $request->user());
        $client->load(['organization.subscription.plan']);

        return $this->success(new ClientResource($client), 201);
    }

    public function show(Client $client): JsonResponse
    {
        $this->authorize('view', $client);

        $client->load(['organization.subscription.plan'])->loadCount('events');

        return $this->success(new ClientResource($client));
    }

    public function update(UpdateClientRequest $request, Client $client, UpdateClientAction $action): JsonResponse
    {
        $this->authorize('update', $client);

        $client = $action->execute($client, $request->validated());
        $client->loadCount('events');

        return $this->success(new ClientResource($client));
    }

    public function destroy(Client $client): JsonResponse
    {
        $this->authorize('delete', $client);

        $client->delete();

        return $this->noContent();
    }

    public function events(Client $client): JsonResponse
    {
        $this->authorize('view', $client);

        $events = $client->events()->latest()->paginate(25);

        return $this->paginated(\App\Modules\Events\Http\Resources\EventResource::collection($events));
    }

    private function resolveOrganizationId($user, ?int $organizationId): ?int
    {
        if ($user->hasAnyRole(['super-admin', 'platform-admin'])) {
            return $organizationId;
        }

        return $user->currentOrganization()?->id ?? 0;
    }
}
