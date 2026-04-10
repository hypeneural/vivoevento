<?php

use App\Modules\Events\Models\Event;
use App\Modules\EventTeam\Models\EventTeamMember;

it('returns event-only session bootstrap with event workspaces and active event context', function () {
    $this->seedPermissions();

    $organization = $this->createOrganization([
        'trade_name' => 'Parceira Alpha',
    ]);

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'title' => 'Casamento Julia e Marcos',
    ]);

    $user = $this->createUser([
        'email' => 'dj-event-only@eventovivo.test',
        'phone' => '5511999887766',
    ]);

    EventTeamMember::query()->create([
        'event_id' => $event->id,
        'user_id' => $user->id,
        'role' => 'operator',
    ]);

    $user->assignRole('viewer');
    $this->actingAs($user);

    $response = $this->apiGet('/auth/me');

    $this->assertApiSuccess($response);

    expect($response->json('data.organization'))->toBeNull();
    expect($response->json('data.user.role.key'))->toBe('viewer');
    expect($response->json('data.access.accessible_modules'))->toContain('dashboard');
    expect($response->json('data.access.accessible_modules'))->toContain('events');
    expect($response->json('data.active_context.type'))->toBe('event');
    expect($response->json('data.active_context.event_id'))->toBe($event->id);
    expect($response->json('data.active_context.entry_path'))->toBe("/my-events/{$event->id}");
    expect($response->json('data.workspaces.organizations'))->toBeArray()->toHaveCount(0);
    expect($response->json('data.workspaces.event_accesses'))->toBeArray()->toHaveCount(1);
    expect($response->json('data.workspaces.event_accesses.0.event_id'))->toBe($event->id);
    expect($response->json('data.workspaces.event_accesses.0.role_key'))->toBe('event.operator');
    expect($response->json('data.workspaces.event_accesses.0.capabilities'))->toContain('moderation');
});
