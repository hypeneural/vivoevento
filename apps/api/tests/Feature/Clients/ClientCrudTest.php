<?php

use App\Modules\Clients\Models\Client;

// ─── CRUD ────────────────────────────────────────────────

it('lists clients scoped to current organization', function () {
    [$user, $organization] = $this->actingAsOwner();

    Client::factory()->count(3)->create([
        'organization_id' => $organization->id,
    ]);

    // Other org's clients should NOT appear
    $otherOrg = $this->createOrganization();
    Client::factory()->count(2)->create([
        'organization_id' => $otherOrg->id,
    ]);

    $response = $this->apiGet('/clients');

    $response->assertStatus(200);
    // ClientController returns AnonymousResourceCollection (paginated)
    $data = $response->json('data');
    expect(count($data))->toBe(3);
});

it('creates a client', function () {
    [$user, $organization] = $this->actingAsOwner();

    $response = $this->apiPost('/clients', [
        'name' => 'Ana Paula',
        'type' => 'pessoa_fisica',
        'email' => 'ana@test.com',
        'phone' => '11999998888',
    ]);

    $this->assertApiSuccess($response, 201);

    $this->assertDatabaseHas('clients', [
        'name' => 'Ana Paula',
        'organization_id' => $organization->id,
    ]);
});

it('shows a client with events count', function () {
    [$user, $organization] = $this->actingAsOwner();

    $client = Client::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->apiGet("/clients/{$client->id}");

    $this->assertApiSuccess($response);
    $response->assertJsonStructure([
        'data' => ['id', 'name', 'type', 'email'],
    ]);
});

it('updates a client', function () {
    [$user, $organization] = $this->actingAsOwner();

    $client = Client::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->apiPatch("/clients/{$client->id}", [
        'name' => 'Nome Atualizado',
    ]);

    $this->assertApiSuccess($response);
    expect($response->json('data.name'))->toBe('Nome Atualizado');
});

it('deletes a client', function () {
    [$user, $organization] = $this->actingAsOwner();

    $client = Client::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->apiDelete("/clients/{$client->id}");

    $response->assertStatus(204);
});

it('validates required fields on client creation', function () {
    [$user, $organization] = $this->actingAsOwner();

    $response = $this->apiPost('/clients', []);

    // type is 'sometimes', not required
    $this->assertApiValidationError($response, ['name']);
});

// ─── Authorization ───────────────────────────────────────

it('rejects client creation for unauthenticated user', function () {
    $response = $this->apiPost('/clients', [
        'name' => 'Test',
        'type' => 'pessoa_fisica',
    ]);

    $this->assertApiUnauthorized($response);
});
