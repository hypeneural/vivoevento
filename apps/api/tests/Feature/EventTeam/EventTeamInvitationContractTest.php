<?php

use App\Modules\Events\Models\Event;
use App\Modules\EventTeam\Models\EventTeamMember;
use App\Modules\Users\Models\User;

it('creates a pending event invitation for an existing platform user without duplicating the user or creating active team membership', function () {
    [$owner, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'title' => 'Casamento da Carol',
    ]);

    $existingUser = User::factory()->create([
        'name' => 'DJ Bruno',
        'email' => 'dj-bruno@eventovivo.test',
        'phone' => '5511998877665',
    ]);
    $existingUser->assignRole('viewer');

    $response = $this->apiPost("/events/{$event->id}/access/invitations", [
        'invitee' => [
            'name' => 'DJ Bruno',
            'email' => 'dj-bruno@eventovivo.test',
            'phone' => '(11) 99887-7665',
        ],
        'preset_key' => 'event.operator',
        'send_via_whatsapp' => false,
    ]);

    $this->assertApiSuccess($response, 201);

    expect($response->json('data.status'))->toBe('pending');
    expect($response->json('data.existing_user_id'))->toBe($existingUser->id);
    expect($response->json('data.persisted_role'))->toBe('operator');
    expect($response->json('data.invitation_url'))->toContain('/convites/eventos/');
    expect(User::query()->count())->toBe(2);

    $this->assertDatabaseHas('event_team_invitations', [
        'event_id' => $event->id,
        'existing_user_id' => $existingUser->id,
        'invitee_phone' => '5511998877665',
        'preset_key' => 'event.operator',
        'persisted_role' => 'operator',
        'status' => 'pending',
    ]);

    $this->assertDatabaseMissing('event_team_members', [
        'event_id' => $event->id,
        'user_id' => $existingUser->id,
    ]);
});

it('lists pending event invitations separately from active event team memberships', function () {
    [$owner, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'title' => 'Evento com equipe',
    ]);

    $activeUser = User::factory()->create([
        'email' => 'active-member@eventovivo.test',
        'phone' => '5511998877666',
    ]);
    $activeUser->assignRole('viewer');

    EventTeamMember::query()->create([
        'event_id' => $event->id,
        'user_id' => $activeUser->id,
        'role' => 'viewer',
    ]);

    $createInvitationResponse = $this->apiPost("/events/{$event->id}/access/invitations", [
        'invitee' => [
            'name' => 'Noiva Julia',
            'phone' => '(11) 99887-7667',
        ],
        'preset_key' => 'event.media-viewer',
        'send_via_whatsapp' => false,
    ]);

    $this->assertApiSuccess($createInvitationResponse, 201);

    $teamResponse = $this->apiGet("/events/{$event->id}/team");
    $invitationResponse = $this->apiGet("/events/{$event->id}/access/invitations");

    $this->assertApiSuccess($teamResponse);
    $this->assertApiSuccess($invitationResponse);

    expect($teamResponse->json('data'))->toHaveCount(1);
    expect($teamResponse->json('data.0.user.id'))->toBe($activeUser->id);
    expect($invitationResponse->json('data'))->toHaveCount(1);
    expect($invitationResponse->json('data.0.status'))->toBe('pending');
    expect($invitationResponse->json('data.0.existing_user_id'))->toBeNull();
});

it('prevents foreign organization owners from creating event invitations outside their own scope', function () {
    [$owner, $organization] = $this->actingAsOwner();

    $foreignOrganization = $this->createOrganization(['trade_name' => 'Cerimonial Externo']);
    $foreignEvent = Event::factory()->create([
        'organization_id' => $foreignOrganization->id,
    ]);

    $response = $this->apiPost("/events/{$foreignEvent->id}/access/invitations", [
        'invitee' => [
            'name' => 'DJ Externo',
            'phone' => '(11) 99887-7668',
        ],
        'preset_key' => 'event.operator',
        'send_via_whatsapp' => false,
    ]);

    $this->assertApiForbidden($response);
});

it('accepts an event invitation without duplicating users or creating organization-wide access')->todo();
it('can resend and revoke a pending event invitation without changing its event scope')->todo();
