<?php

namespace App\Modules\Clients\Http\Controllers;

use App\Modules\Clients\Http\Requests\StoreClientRequest;
use App\Modules\Clients\Http\Requests\UpdateClientRequest;
use App\Modules\Clients\Http\Resources\ClientResource;
use App\Modules\Clients\Models\Client;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClientController extends BaseController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $orgId = $user->currentOrganization()?->id;

        $clients = Client::where('organization_id', $orgId)
            ->withCount('events')
            ->latest()
            ->paginate(25);

        return ClientResource::collection($clients);
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $user = $request->user();

        $client = Client::create([
            ...$request->validated(),
            'organization_id' => $user->currentOrganization()?->id,
            'created_by' => $user->id,
        ]);

        return $this->success(new ClientResource($client), 201);
    }

    public function show(Client $client): JsonResponse
    {
        $client->loadCount('events');

        return $this->success(new ClientResource($client));
    }

    public function update(UpdateClientRequest $request, Client $client): JsonResponse
    {
        $client->update($request->validated());

        return $this->success(new ClientResource($client->fresh()));
    }

    public function destroy(Client $client): JsonResponse
    {
        $client->delete();

        return $this->success(null, 204);
    }

    public function events(Client $client): AnonymousResourceCollection
    {
        $events = $client->events()->latest()->paginate(25);

        return \App\Modules\Events\Http\Resources\EventResource::collection($events);
    }
}
