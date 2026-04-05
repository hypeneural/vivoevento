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
    $response->assertJsonStructure([
        'data',
        'meta' => ['page', 'per_page', 'total', 'last_page', 'request_id'],
    ]);

    $data = $response->json('data');
    expect(count($data))->toBe(3);
});

it('filters clients by search, type and events presence', function () {
    [$user, $organization] = $this->actingAsOwner();

    $company = Client::factory()->empresa()->create([
        'organization_id' => $organization->id,
        'name' => 'Empresa Horizonte',
    ]);

    Client::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Mariana Lopes',
    ]);

    \App\Modules\Events\Models\Event::factory()->create([
        'organization_id' => $organization->id,
        'client_id' => $company->id,
    ]);

    $response = $this->apiGet('/clients?search=Horizonte&type=empresa&has_events=1');

    $this->assertApiSuccess($response);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('Empresa Horizonte');
});

it('allows super-admin to list clients across organizations and filter by plan', function () {
    [$user] = $this->actingAsSuperAdmin();

    $starter = \App\Modules\Plans\Models\Plan::create([
        'code' => 'starter-plan',
        'name' => 'Starter',
        'audience' => 'b2b',
        'status' => 'active',
    ]);

    $business = \App\Modules\Plans\Models\Plan::create([
        'code' => 'business-plan',
        'name' => 'Business',
        'audience' => 'b2b',
        'status' => 'active',
    ]);

    $starterOrg = $this->createOrganization(['slug' => 'starter-org']);
    $businessOrg = $this->createOrganization(['slug' => 'business-org']);

    \App\Modules\Billing\Models\Subscription::create([
        'organization_id' => $starterOrg->id,
        'plan_id' => $starter->id,
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'starts_at' => now(),
        'renews_at' => now()->addMonth(),
    ]);

    \App\Modules\Billing\Models\Subscription::create([
        'organization_id' => $businessOrg->id,
        'plan_id' => $business->id,
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'starts_at' => now(),
        'renews_at' => now()->addMonth(),
    ]);

    Client::factory()->create([
        'organization_id' => $starterOrg->id,
        'name' => 'Cliente Starter',
    ]);

    Client::factory()->create([
        'organization_id' => $businessOrg->id,
        'name' => 'Cliente Business',
    ]);

    $response = $this->apiGet('/clients?plan_code=business-plan');

    $this->assertApiSuccess($response);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('Cliente Business');
    expect($response->json('data.0.organization_billing.plan_key'))->toBe('business-plan');
    expect($response->json('data.0.plan_key'))->toBe('business-plan');
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
        'data' => ['id', 'name', 'type', 'email', 'organization_name', 'organization_billing'],
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

it('rejects access to a client from another organization', function () {
    [$user, $organization] = $this->actingAsOwner();

    $otherOrg = $this->createOrganization();
    $client = Client::factory()->create([
        'organization_id' => $otherOrg->id,
    ]);

    $response = $this->apiGet("/clients/{$client->id}");

    $response->assertStatus(403);
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
