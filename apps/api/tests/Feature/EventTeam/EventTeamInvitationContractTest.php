<?php

use App\Modules\Events\Models\Event;
use App\Modules\EventTeam\Models\EventTeamInvitation;
use App\Modules\EventTeam\Models\EventTeamMember;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Users\Models\User;
use App\Modules\WhatsApp\Jobs\SendWhatsAppMessageJob;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use Illuminate\Support\Facades\Queue;

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

it('shows the public event invitation context without exposing organization-wide data', function () {
    [$owner, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'title' => 'Casamento da Lara',
    ]);

    $createResponse = $this->apiPost("/events/{$event->id}/access/invitations", [
        'invitee' => [
            'name' => 'Noiva Lara',
            'phone' => '(11) 99887-7669',
        ],
        'preset_key' => 'event.media-viewer',
        'send_via_whatsapp' => false,
    ]);

    $this->assertApiSuccess($createResponse, 201);

    $token = $createResponse->json('data.invitation_url');
    $token = basename((string) $token);

    $response = $this->apiGet("/public/event-invitations/{$token}");

    $this->assertApiSuccess($response);
    expect($response->json('data.event.title'))->toBe('Casamento da Lara');
    expect($response->json('data.organization.name'))->toBe($organization->trade_name);
    expect($response->json('data.access.role_label'))->toBeString()->toContain('Ver');
    expect($response->json('data.access.capabilities'))->toContain('overview', 'media');
    expect($response->json('data.requires_existing_login'))->toBeFalse();
});

it('accepts an event invitation for a new platform user without duplicating users or creating organization-wide access', function () {
    [$owner, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'title' => 'Evento Publico Convite',
    ]);

    $createResponse = $this->apiPost("/events/{$event->id}/access/invitations", [
        'invitee' => [
            'name' => 'DJ Novo',
            'phone' => '(11) 99887-7670',
            'email' => 'dj-novo@eventovivo.test',
        ],
        'preset_key' => 'event.operator',
        'send_via_whatsapp' => false,
    ]);

    $this->assertApiSuccess($createResponse, 201);

    $token = basename((string) $createResponse->json('data.invitation_url'));

    $acceptResponse = $this->apiPost("/public/event-invitations/{$token}/accept", [
        'password' => 'SenhaForte123!',
        'password_confirmation' => 'SenhaForte123!',
        'device_name' => 'web-invite',
    ]);

    $this->assertApiSuccess($acceptResponse);

    $acceptedUser = User::query()->where('phone', '5511998877670')->firstOrFail();

    expect($acceptResponse->json('data.accepted'))->toBeTrue();
    expect($acceptResponse->json('data.next_path'))->toBe("/my-events/{$event->id}");
    expect($acceptResponse->json('data.token'))->toBeString()->not->toBe('');
    expect($acceptResponse->json('data.session.active_context.event_id'))->toBe($event->id);

    $this->assertDatabaseHas('event_team_members', [
        'event_id' => $event->id,
        'user_id' => $acceptedUser->id,
        'role' => 'operator',
    ]);
    $this->assertDatabaseHas('event_team_invitations', [
        'event_id' => $event->id,
        'accepted_user_id' => $acceptedUser->id,
        'status' => 'accepted',
    ]);
    $this->assertDatabaseMissing('organization_members', [
        'organization_id' => $organization->id,
        'user_id' => $acceptedUser->id,
    ]);
    expect(User::query()->count())->toBe(2);
});

it('accepts an event invitation for an existing authenticated user without duplicating users or creating organization-wide access', function () {
    [$owner, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'title' => 'Evento DJ Existente',
    ]);

    $existingUser = User::factory()->create([
        'name' => 'DJ Existente',
        'email' => 'dj-existente@eventovivo.test',
        'phone' => '5511998877671',
    ]);
    $existingUser->assignRole('viewer');

    $createResponse = $this->apiPost("/events/{$event->id}/access/invitations", [
        'invitee' => [
            'name' => 'DJ Existente',
            'phone' => '(11) 99887-7671',
            'email' => 'dj-existente@eventovivo.test',
        ],
        'preset_key' => 'event.moderator',
        'send_via_whatsapp' => false,
    ]);

    $this->assertApiSuccess($createResponse, 201);

    $token = basename((string) $createResponse->json('data.invitation_url'));

    $this->actingAs($existingUser);

    $acceptResponse = $this->apiPost("/event-invitations/{$token}/accept");

    $this->assertApiSuccess($acceptResponse);

    expect($acceptResponse->json('data.token'))->toBeNull();
    expect($acceptResponse->json('data.session.active_context.event_id'))->toBe($event->id);
    expect(User::query()->count())->toBe(2);

    $this->assertDatabaseHas('event_team_members', [
        'event_id' => $event->id,
        'user_id' => $existingUser->id,
        'role' => 'moderator',
    ]);
    $this->assertDatabaseHas('event_team_invitations', [
        'event_id' => $event->id,
        'accepted_user_id' => $existingUser->id,
        'status' => 'accepted',
    ]);
    $this->assertDatabaseMissing('organization_members', [
        'organization_id' => $organization->id,
        'user_id' => $existingUser->id,
    ]);
});

it('queues whatsapp delivery when creating a pending event invitation with an event sender configured', function () {
    Queue::fake();

    [$owner, $organization] = $this->actingAsOwner();

    $organizationDefaultSender = WhatsAppInstance::factory()->connected()->create([
        'organization_id' => $organization->id,
        'is_default' => true,
    ]);

    $eventSender = WhatsAppInstance::factory()->connected()->create([
        'organization_id' => $organization->id,
        'is_default' => false,
    ]);

    WhatsAppInstance::factory()->connected()->create([
        'is_default' => true,
    ]);

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'title' => 'Evento com acesso no WhatsApp',
        'default_whatsapp_instance_id' => $eventSender->id,
    ]);

    $response = $this->apiPost("/events/{$event->id}/access/invitations", [
        'invitee' => [
            'name' => 'DJ Thiago',
            'phone' => '(11) 99887-7672',
        ],
        'preset_key' => 'event.operator',
        'send_via_whatsapp' => true,
    ]);

    $this->assertApiSuccess($response, 201);

    expect($response->json('data.delivery_channel'))->toBe('whatsapp');
    expect($response->json('data.delivery_status'))->toBe('queued');
    expect($response->json('data.last_sent_at'))->not->toBeNull();

    $this->assertDatabaseHas('whatsapp_messages', [
        'instance_id' => $eventSender->id,
        'recipient_phone' => '5511998877672',
    ]);

    $this->assertDatabaseMissing('whatsapp_messages', [
        'instance_id' => $organizationDefaultSender->id,
        'recipient_phone' => '5511998877672',
    ]);

    Queue::assertPushed(SendWhatsAppMessageJob::class);
});

it('can resend a pending event invitation with a rotated token and organization whatsapp fallback', function () {
    Queue::fake();

    [$owner, $organization] = $this->actingAsOwner();

    $organizationSender = WhatsAppInstance::factory()->connected()->create([
        'organization_id' => $organization->id,
        'is_default' => true,
    ]);

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'title' => 'Evento com fallback da organizacao',
        'default_whatsapp_instance_id' => null,
    ]);

    $createResponse = $this->apiPost("/events/{$event->id}/access/invitations", [
        'invitee' => [
            'name' => 'Noiva Isabela',
            'phone' => '(11) 99887-7673',
        ],
        'preset_key' => 'event.media-viewer',
        'send_via_whatsapp' => false,
    ]);

    $this->assertApiSuccess($createResponse, 201);

    $invitation = EventTeamInvitation::query()->findOrFail($createResponse->json('data.id'));
    $previousToken = $invitation->token;

    $resendResponse = $this->apiPost("/events/{$event->id}/access/invitations/{$invitation->id}/resend", [
        'send_via_whatsapp' => true,
    ]);

    $this->assertApiSuccess($resendResponse);

    $invitation->refresh();

    expect($invitation->status)->toBe(EventTeamInvitation::STATUS_PENDING);
    expect($invitation->token)->not->toBe($previousToken);
    expect($invitation->delivery_channel)->toBe('whatsapp');
    expect($invitation->delivery_status)->toBe('queued');
    expect($invitation->last_sent_at)->not->toBeNull();
    expect($resendResponse->json('data.invitation_url'))->toContain($invitation->token);

    $this->assertDatabaseHas('whatsapp_messages', [
        'instance_id' => $organizationSender->id,
        'recipient_phone' => '5511998877673',
    ]);

    Queue::assertPushed(SendWhatsAppMessageJob::class);
});

it('can revoke a pending event invitation and block future public access to the token', function () {
    [$owner, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'title' => 'Evento com revogacao',
    ]);

    $createResponse = $this->apiPost("/events/{$event->id}/access/invitations", [
        'invitee' => [
            'name' => 'DJ Henrique',
            'phone' => '(11) 99887-7674',
        ],
        'preset_key' => 'event.moderator',
        'send_via_whatsapp' => false,
    ]);

    $this->assertApiSuccess($createResponse, 201);

    $invitation = EventTeamInvitation::query()->findOrFail($createResponse->json('data.id'));

    $revokeResponse = $this->apiPost("/events/{$event->id}/access/invitations/{$invitation->id}/revoke");

    $this->assertApiSuccess($revokeResponse);

    $invitation->refresh();

    expect($invitation->status)->toBe(EventTeamInvitation::STATUS_REVOKED);
    expect($invitation->revoked_at)->not->toBeNull();
    expect($revokeResponse->json('data.status'))->toBe('revoked');

    $publicResponse = $this->apiGet("/public/event-invitations/{$invitation->token}");
    $publicResponse->assertStatus(410);
});

it('forbids foreign organization owners from resending or revoking event invitations outside their scope', function () {
    [$owner, $organization] = $this->actingAsOwner();

    $foreignOrganization = $this->createOrganization(['trade_name' => 'Cerimonial Externo']);
    $foreignEvent = Event::factory()->create([
        'organization_id' => $foreignOrganization->id,
        'title' => 'Evento externo',
    ]);

    $foreignInvitation = EventTeamInvitation::factory()->create([
        'event_id' => $foreignEvent->id,
        'organization_id' => $foreignOrganization->id,
        'status' => EventTeamInvitation::STATUS_PENDING,
    ]);

    $resendResponse = $this->apiPost("/events/{$foreignEvent->id}/access/invitations/{$foreignInvitation->id}/resend", [
        'send_via_whatsapp' => false,
    ]);
    $revokeResponse = $this->apiPost("/events/{$foreignEvent->id}/access/invitations/{$foreignInvitation->id}/revoke");

    $this->assertApiForbidden($resendResponse);
    $this->assertApiForbidden($revokeResponse);
});
