<?php

use App\Modules\Events\Models\Event;
use App\Modules\EventTeam\Models\EventTeamMember;
use App\Modules\Organizations\Models\OrganizationMember;

it('returns available organization workspaces for a user linked to multiple organizations instead of collapsing the session to the first membership', function () {
    $this->seedPermissions();

    $organizationA = $this->createOrganization(['trade_name' => 'Parceira Norte']);
    $organizationB = $this->createOrganization(['trade_name' => 'Parceira Sul']);

    $user = $this->createUser([
        'email' => 'multi-org-workspaces@eventovivo.test',
        'phone' => '5511988877001',
    ]);
    $user->assignRole('partner-manager');

    OrganizationMember::query()->create([
        'organization_id' => $organizationA->id,
        'user_id' => $user->id,
        'role_key' => 'partner-manager',
        'is_owner' => false,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    OrganizationMember::query()->create([
        'organization_id' => $organizationB->id,
        'user_id' => $user->id,
        'role_key' => 'partner-manager',
        'is_owner' => false,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $this->actingAs($user);

    $response = $this->apiGet('/auth/me');

    $this->assertApiSuccess($response);
    expect($response->json('data.organization.id'))->toBe($organizationA->id);
    expect($response->json('data.active_context.type'))->toBe('organization');
    expect($response->json('data.workspaces.organizations'))->toHaveCount(2);
    expect(collect($response->json('data.workspaces.organizations'))->pluck('organization_id')->all())
        ->toContain($organizationA->id, $organizationB->id);
});

it('allows switching the active organization context without creating duplicate user accounts', function () {
    $this->seedPermissions();

    $organizationA = $this->createOrganization(['trade_name' => 'Parceira A']);
    $organizationB = $this->createOrganization(['trade_name' => 'Parceira B']);

    $user = $this->createUser([
        'email' => 'switch-context@eventovivo.test',
        'phone' => '5511988877002',
    ]);
    $user->assignRole('partner-manager');

    foreach ([$organizationA, $organizationB] as $organization) {
        OrganizationMember::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_key' => 'partner-manager',
            'is_owner' => false,
            'status' => 'active',
            'joined_at' => now(),
        ]);
    }

    $this->actingAs($user);

    $response = $this->apiPost('/auth/context/organization', [
        'organization_id' => $organizationB->id,
    ]);

    $this->assertApiSuccess($response);
    $user->refresh();

    expect($response->json('data.organization.id'))->toBe($organizationB->id);
    expect($response->json('data.active_context.organization_id'))->toBe($organizationB->id);
    expect(data_get($user->preferences, 'active_context.organization_id'))->toBe($organizationB->id);
    expect(\App\Modules\Users\Models\User::query()->count())->toBe(1);
});

it('keeps event-only invitations attachable to an existing platform user identified by whatsapp or email', function () {
    $this->seedPermissions();

    [$owner, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'title' => 'Evento com DJ existente',
    ]);

    $existingUser = $this->createUser([
        'name' => 'DJ Marcelo',
        'email' => 'dj-marcelo@eventovivo.test',
        'phone' => '5511988877009',
    ]);
    $existingUser->assignRole('viewer');

    $response = $this->apiPost("/events/{$event->id}/access/invitations", [
        'invitee' => [
            'name' => 'DJ Marcelo',
            'email' => 'dj-marcelo@eventovivo.test',
            'phone' => '(11) 98887-7009',
        ],
        'preset_key' => 'event.operator',
        'send_via_whatsapp' => false,
    ]);

    $this->assertApiSuccess($response, 201);

    expect($response->json('data.existing_user_id'))->toBe($existingUser->id);
    expect(\App\Modules\Users\Models\User::query()->count())->toBe(2);
});

it('returns four event workspaces for the same dj across different partner organizations with per-event capabilities', function () {
    $this->seedPermissions();

    $dj = $this->createUser([
        'email' => 'dj-multi-event@eventovivo.test',
        'phone' => '5511988877003',
    ]);
    $dj->assignRole('viewer');
    $this->actingAs($dj);

    $events = collect([
        ['partner' => 'Cerimonial Aurora', 'title' => 'Casamento Ana e Joao', 'role' => 'operator'],
        ['partner' => 'Cerimonial Aurora', 'title' => 'Casamento Maria e Leo', 'role' => 'moderator'],
        ['partner' => 'Bella Assessoria', 'title' => 'Casamento Gabi e Rafa', 'role' => 'operator'],
        ['partner' => 'Luz Cerimonial', 'title' => 'Festa Sofia 15', 'role' => 'viewer'],
    ])->map(function (array $definition) use ($dj) {
        $organization = $this->createOrganization(['trade_name' => $definition['partner']]);
        $event = Event::factory()->create([
            'organization_id' => $organization->id,
            'title' => $definition['title'],
        ]);

        EventTeamMember::query()->create([
            'event_id' => $event->id,
            'user_id' => $dj->id,
            'role' => $definition['role'],
        ]);

        return [$organization, $event, $definition];
    });

    $response = $this->apiGet('/auth/me');

    $this->assertApiSuccess($response);
    expect($response->json('data.organization'))->toBeNull();
    expect($response->json('data.workspaces.organizations'))->toHaveCount(0);
    expect($response->json('data.workspaces.event_accesses'))->toHaveCount(4);
    expect(collect($response->json('data.workspaces.event_accesses'))->pluck('organization_name')->unique()->values()->all())
        ->toContain('Cerimonial Aurora', 'Bella Assessoria', 'Luz Cerimonial');
    expect(collect($response->json('data.workspaces.event_accesses'))->pluck('role_key')->all())
        ->toContain('event.operator', 'event.moderator', 'event.media-viewer');
});

it('groups event workspaces by partner organization without granting organization-wide access to the dj', function () {
    $this->seedPermissions();

    $dj = $this->createUser([
        'email' => 'dj-grouped-workspaces@eventovivo.test',
        'phone' => '5511988877010',
    ]);
    $dj->assignRole('viewer');
    $this->actingAs($dj);

    foreach ([
        ['partner' => 'Cerimonial Aurora', 'title' => 'Casamento A', 'role' => 'operator'],
        ['partner' => 'Cerimonial Aurora', 'title' => 'Casamento B', 'role' => 'moderator'],
        ['partner' => 'Bella Assessoria', 'title' => 'Casamento C', 'role' => 'viewer'],
    ] as $definition) {
        $organization = $this->createOrganization(['trade_name' => $definition['partner']]);
        $event = Event::factory()->create([
            'organization_id' => $organization->id,
            'title' => $definition['title'],
        ]);

        EventTeamMember::query()->create([
            'event_id' => $event->id,
            'user_id' => $dj->id,
            'role' => $definition['role'],
        ]);
    }

    $response = $this->apiGet('/auth/me');

    $this->assertApiSuccess($response);

    $grouped = collect($response->json('data.workspaces.event_accesses'))
        ->groupBy('organization_name')
        ->map->count()
        ->all();

    expect($response->json('data.organization'))->toBeNull();
    expect($response->json('data.workspaces.organizations'))->toHaveCount(0);
    expect($grouped)->toMatchArray([
        'Cerimonial Aurora' => 2,
        'Bella Assessoria' => 1,
    ]);
});

it('uses the selected event context to scope media moderation wall and play endpoints for an event-scoped user', function () {
    $this->seedPermissions();

    $organization = $this->createOrganization(['trade_name' => 'Cerimonial Contexto']);

    $operatorEvent = Event::factory()->create([
        'organization_id' => $organization->id,
        'title' => 'Evento Operador',
    ]);

    $viewerEvent = Event::factory()->create([
        'organization_id' => $organization->id,
        'title' => 'Evento Midias',
    ]);

    $user = $this->createUser([
        'email' => 'event-context-scoping@eventovivo.test',
        'phone' => '5511988877011',
    ]);
    $user->assignRole('viewer');
    $this->actingAs($user);

    EventTeamMember::query()->create([
        'event_id' => $operatorEvent->id,
        'user_id' => $user->id,
        'role' => 'operator',
    ]);

    EventTeamMember::query()->create([
        'event_id' => $viewerEvent->id,
        'user_id' => $user->id,
        'role' => 'viewer',
    ]);

    $viewerContextResponse = $this->apiPost('/auth/context/event', [
        'event_id' => $viewerEvent->id,
    ]);

    $this->assertApiSuccess($viewerContextResponse);
    expect($viewerContextResponse->json('data.active_context.event_id'))->toBe($viewerEvent->id);
    expect($viewerContextResponse->json('data.active_context.entry_path'))->toBe("/my-events/{$viewerEvent->id}");
    expect($viewerContextResponse->json('data.active_context.capabilities'))->toBe(['overview', 'media']);

    $operatorContextResponse = $this->apiPost('/auth/context/event', [
        'event_id' => $operatorEvent->id,
    ]);

    $this->assertApiSuccess($operatorContextResponse);
    expect($operatorContextResponse->json('data.active_context.event_id'))->toBe($operatorEvent->id);
    expect($operatorContextResponse->json('data.active_context.entry_path'))->toBe("/my-events/{$operatorEvent->id}");
    expect($operatorContextResponse->json('data.active_context.capabilities'))
        ->toContain('overview', 'media', 'moderation', 'wall', 'play');
});

it('returns enough event workspace metadata for frontend filters without exposing unrelated organizations', function () {
    $this->seedPermissions();

    $organization = $this->createOrganization(['trade_name' => 'Cerimonial Prime']);
    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'title' => 'Casamento Workspace',
        'status' => \App\Modules\Events\Enums\EventStatus::Active,
        'starts_at' => now()->addDays(15),
    ]);

    $user = $this->createUser([
        'email' => 'workspace-metadata@eventovivo.test',
        'phone' => '5511988877004',
    ]);
    $user->assignRole('viewer');
    $this->actingAs($user);

    EventTeamMember::query()->create([
        'event_id' => $event->id,
        'user_id' => $user->id,
        'role' => 'operator',
    ]);

    $response = $this->apiGet('/auth/me');

    $this->assertApiSuccess($response);

    $workspace = $response->json('data.workspaces.event_accesses.0');

    expect($workspace)->toHaveKeys([
        'event_id',
        'event_uuid',
        'event_title',
        'event_slug',
        'event_date',
        'event_status',
        'organization_id',
        'organization_name',
        'organization_slug',
        'role_key',
        'role_label',
        'persisted_role',
        'capabilities',
        'entry_path',
    ]);
    expect($workspace)->not->toHaveKey('permissions');
    expect($workspace['event_id'])->toBe($event->id);
    expect($workspace['organization_name'])->toBe('Cerimonial Prime');
    expect($workspace['entry_path'])->toBe("/my-events/{$event->id}");
});

it('redirects event-only sessions through an active event context instead of a current organization', function () {
    $this->seedPermissions();

    $organization = $this->createOrganization(['trade_name' => 'Cerimonial Redireciona']);
    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'title' => 'Evento Unico',
    ]);

    $user = $this->createUser([
        'email' => 'event-only-redirect@eventovivo.test',
        'phone' => '5511988877005',
    ]);
    $user->assignRole('viewer');
    $this->actingAs($user);

    EventTeamMember::query()->create([
        'event_id' => $event->id,
        'user_id' => $user->id,
        'role' => 'viewer',
    ]);

    $response = $this->apiGet('/auth/me');

    $this->assertApiSuccess($response);
    expect($response->json('data.organization'))->toBeNull();
    expect($response->json('data.active_context.type'))->toBe('event');
    expect($response->json('data.active_context.event_id'))->toBe($event->id);
    expect($response->json('data.active_context.entry_path'))->toBe("/my-events/{$event->id}");
});
