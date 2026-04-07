<?php

it('forbids partner owners from listing organizations through the global admin endpoint', function () {
    $this->actingAsOwner();
    $this->createOrganization([
        'trade_name' => 'Outra Organizacao',
    ]);

    $response = $this->apiGet('/organizations');

    $response->assertForbidden();
});

it('forbids partner owners from inspecting another organization through the admin show endpoint', function () {
    $otherOrganization = $this->createOrganization([
        'trade_name' => 'Organizacao Externa',
    ]);

    $this->actingAsOwner();

    $response = $this->apiGet("/organizations/{$otherOrganization->id}");

    $response->assertForbidden();
});

it('forbids viewers from updating another organization through the admin endpoint', function () {
    $targetOrganization = $this->createOrganization([
        'trade_name' => 'Org Original',
    ]);

    $this->actingAsViewer();

    $response = $this->apiPatch("/organizations/{$targetOrganization->id}", [
        'name' => 'Org Alterada por Viewer',
    ]);

    $response->assertForbidden();

    $targetOrganization->refresh();

    expect($targetOrganization->trade_name)->toBe('Org Original');
});

it('forbids viewers from soft deleting another organization through the admin endpoint', function () {
    $targetOrganization = $this->createOrganization([
        'trade_name' => 'Org para delete',
    ]);

    $this->actingAsViewer();

    $response = $this->apiDelete("/organizations/{$targetOrganization->id}");

    $response->assertForbidden();
    $this->assertDatabaseHas('organizations', ['id' => $targetOrganization->id]);
});

it('allows super admins to manage organizations through the global admin endpoints', function () {
    [$admin] = $this->actingAsSuperAdmin();
    $organization = $this->createOrganization([
        'trade_name' => 'Org Admin',
    ]);

    $listResponse = $this->apiGet('/organizations');
    $showResponse = $this->apiGet("/organizations/{$organization->id}");
    $updateResponse = $this->apiPatch("/organizations/{$organization->id}", [
        'name' => 'Org Admin Atualizada',
    ]);

    $this->assertApiSuccess($listResponse);
    $this->assertApiSuccess($showResponse);
    $this->assertApiSuccess($updateResponse);

    $organization->refresh();

    expect($organization->trade_name)->toBe('Org Admin Atualizada');
});
