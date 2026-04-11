<?php

use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Organizations\Models\OrganizationMemberInvitation;
use App\Modules\Users\Models\User;
use App\Modules\WhatsApp\Jobs\SendWhatsAppMessageJob;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use Illuminate\Support\Facades\Queue;

it('creates a pending invitation with token and invitation url instead of an active membership', function () {
    [$owner, $organization] = $this->actingAsOwner();

    $existingUser = User::factory()->create([
        'name' => 'Secretaria Clara',
        'email' => 'clara@eventovivo.test',
        'phone' => '5511999001001',
    ]);
    $existingUser->assignRole('viewer');

    $response = $this->apiPost('/organizations/current/team', [
        'user' => [
            'name' => 'Secretaria Clara',
            'email' => 'clara@eventovivo.test',
            'phone' => '(11) 99901-0001',
        ],
        'role_key' => 'partner-manager',
        'send_via_whatsapp' => false,
    ]);

    $this->assertApiSuccess($response, 201);

    expect($response->json('data.status'))->toBe('pending');
    expect($response->json('data.existing_user_id'))->toBe($existingUser->id);
    expect($response->json('data.invitation_url'))->toContain('/convites/equipe/');
    expect(User::query()->count())->toBe(2);

    $this->assertDatabaseHas('organization_member_invitations', [
        'organization_id' => $organization->id,
        'existing_user_id' => $existingUser->id,
        'invitee_phone' => '5511999010001',
        'role_key' => 'partner-manager',
        'status' => 'pending',
    ]);

    $this->assertDatabaseMissing('organization_members', [
        'organization_id' => $organization->id,
        'user_id' => $existingUser->id,
        'status' => 'active',
    ]);
});

it('dispatches invitation delivery through the current organization default whatsapp instance when requested', function () {
    Queue::fake();

    [$owner, $organization] = $this->actingAsOwner();

    $organizationSender = WhatsAppInstance::factory()->connected()->create([
        'organization_id' => $organization->id,
        'is_default' => true,
    ]);

    WhatsAppInstance::factory()->connected()->create([
        'is_default' => true,
    ]);

    $response = $this->apiPost('/organizations/current/team', [
        'user' => [
            'name' => 'Equipe no WhatsApp',
            'phone' => '(11) 99901-0002',
        ],
        'role_key' => 'event-operator',
        'send_via_whatsapp' => true,
    ]);

    $this->assertApiSuccess($response, 201);

    expect($response->json('data.delivery_channel'))->toBe('whatsapp');
    expect($response->json('data.delivery_status'))->toBe('queued');
    expect($response->json('data.last_sent_at'))->not->toBeNull();

    $this->assertDatabaseHas('whatsapp_messages', [
        'instance_id' => $organizationSender->id,
        'recipient_phone' => '5511999010002',
    ]);

    Queue::assertPushed(SendWhatsAppMessageJob::class);
});

it('still returns a manual invitation url when the organization has no connected whatsapp instance', function () {
    [$owner, $organization] = $this->actingAsOwner();

    $response = $this->apiPost('/organizations/current/team', [
        'user' => [
            'name' => 'Entrega manual',
            'phone' => '(11) 99901-0003',
        ],
        'role_key' => 'viewer',
        'send_via_whatsapp' => true,
    ]);

    $this->assertApiSuccess($response, 201);

    expect($response->json('data.delivery_channel'))->toBe('whatsapp');
    expect($response->json('data.delivery_status'))->toBe('unavailable');
    expect($response->json('data.delivery_error'))->toBe('whatsapp_instance_unavailable');
    expect($response->json('data.invitation_url'))->toContain('/convites/equipe/');
});

it('accepts a team invitation without creating a new organization for the invited user', function () {
    [$owner, $organization] = $this->actingAsOwner();

    $organizationsBefore = Organization::query()->count();

    $createResponse = $this->apiPost('/organizations/current/team', [
        'user' => [
            'name' => 'Financeiro Novo',
            'email' => 'financeiro-novo@eventovivo.test',
            'phone' => '(11) 99901-0004',
        ],
        'role_key' => 'financeiro',
        'send_via_whatsapp' => false,
    ]);

    $this->assertApiSuccess($createResponse, 201);

    $token = basename((string) $createResponse->json('data.invitation_url'));

    $acceptResponse = $this->apiPost("/public/organization-invitations/{$token}/accept", [
        'password' => 'SenhaForte123!',
        'password_confirmation' => 'SenhaForte123!',
        'device_name' => 'web-invite',
    ]);

    $this->assertApiSuccess($acceptResponse);

    $acceptedUser = User::query()->where('phone', '5511999010004')->firstOrFail();

    expect(Organization::query()->count())->toBe($organizationsBefore);
    expect($acceptResponse->json('data.accepted'))->toBeTrue();
    expect($acceptResponse->json('data.next_path'))->toBe('/');
    expect($acceptResponse->json('data.token'))->toBeString()->not->toBe('');
    expect($acceptResponse->json('data.session.active_context.organization_id'))->toBe($organization->id);

    $this->assertDatabaseHas('organization_members', [
        'organization_id' => $organization->id,
        'user_id' => $acceptedUser->id,
        'role_key' => 'financeiro',
        'status' => 'active',
    ]);
    $this->assertDatabaseHas('organization_member_invitations', [
        'organization_id' => $organization->id,
        'accepted_user_id' => $acceptedUser->id,
        'status' => 'accepted',
    ]);
});

it('requires a dedicated ownership transfer flow instead of promoting owners through the generic team invitation endpoint', function () {
    [$owner, $organization] = $this->actingAsOwner();

    $response = $this->apiPost('/organizations/current/team', [
        'user' => [
            'name' => 'Novo Proprietario',
            'email' => 'novo-owner@eventovivo.test',
            'phone' => '(11) 99901-0005',
        ],
        'role_key' => 'partner-owner',
        'send_via_whatsapp' => false,
    ]);

    $this->assertApiValidationError($response, ['role_key']);
});

it('lists pending invitations separately from active team memberships in settings', function () {
    [$owner, $organization] = $this->actingAsOwner();

    $activeUser = User::factory()->create([
        'email' => 'active-org-member@eventovivo.test',
        'phone' => '5511999001006',
    ]);
    $activeUser->assignRole('partner-manager');

    OrganizationMember::query()->create([
        'organization_id' => $organization->id,
        'user_id' => $activeUser->id,
        'role_key' => 'partner-manager',
        'is_owner' => false,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $createResponse = $this->apiPost('/organizations/current/team', [
        'user' => [
            'name' => 'Convite Pendente',
            'phone' => '(11) 99901-0007',
        ],
        'role_key' => 'viewer',
        'send_via_whatsapp' => false,
    ]);

    $this->assertApiSuccess($createResponse, 201);

    $teamResponse = $this->apiGet('/organizations/current/team');
    $invitationResponse = $this->apiGet('/organizations/current/team/invitations');

    $this->assertApiSuccess($teamResponse);
    $this->assertApiSuccess($invitationResponse);

    expect($teamResponse->json('data'))->toHaveCount(2);
    expect(collect($teamResponse->json('data'))->pluck('user.id')->all())->toContain($owner->id, $activeUser->id);
    expect($invitationResponse->json('data'))->toHaveCount(1);
    expect($invitationResponse->json('data.0.status'))->toBe('pending');
});

it('can resend and revoke an organization team invitation with rotated token and public invalidation', function () {
    Queue::fake();

    [$owner, $organization] = $this->actingAsOwner();

    $organizationSender = WhatsAppInstance::factory()->connected()->create([
        'organization_id' => $organization->id,
        'is_default' => true,
    ]);

    $createResponse = $this->apiPost('/organizations/current/team', [
        'user' => [
            'name' => 'Convite Reenviavel',
            'phone' => '(11) 99901-0008',
        ],
        'role_key' => 'viewer',
        'send_via_whatsapp' => false,
    ]);

    $this->assertApiSuccess($createResponse, 201);

    $invitation = OrganizationMemberInvitation::query()->findOrFail($createResponse->json('data.id'));
    $previousToken = $invitation->token;

    $resendResponse = $this->apiPost("/organizations/current/team/invitations/{$invitation->id}/resend", [
        'send_via_whatsapp' => true,
    ]);

    $this->assertApiSuccess($resendResponse);

    $invitation->refresh();

    expect($invitation->token)->not->toBe($previousToken);
    expect($invitation->delivery_status)->toBe('queued');
    expect($resendResponse->json('data.invitation_url'))->toContain($invitation->token);

    $this->assertDatabaseHas('whatsapp_messages', [
        'instance_id' => $organizationSender->id,
        'recipient_phone' => '5511999010008',
    ]);

    $revokeResponse = $this->apiPost("/organizations/current/team/invitations/{$invitation->id}/revoke");

    $this->assertApiSuccess($revokeResponse);

    $invitation->refresh();

    expect($invitation->status)->toBe(OrganizationMemberInvitation::STATUS_REVOKED);
    expect($invitation->revoked_at)->not->toBeNull();

    $publicResponse = $this->apiGet("/public/organization-invitations/{$invitation->token}");
    $publicResponse->assertStatus(410);
});
