<?php

use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Organizations\Models\OrganizationMemberInvitation;
use App\Modules\Users\Models\User;
use App\Modules\WhatsApp\Jobs\SendWhatsAppMessageJob;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use Illuminate\Support\Facades\Queue;

it('characterizes that the current team add flow now creates a pending invitation instead of immediate active membership', function () {
    [$owner, $organization] = $this->actingAsOwner();

    $response = $this->apiPost('/organizations/current/team', [
        'user' => [
            'name' => 'Convite Imediato',
            'email' => 'convite-imediato@eventovivo.test',
            'phone' => '11999990001',
        ],
        'role_key' => 'partner-manager',
        'send_via_whatsapp' => false,
    ]);

    $this->assertApiSuccess($response, 201);

    $invitation = OrganizationMemberInvitation::query()->findOrFail($response->json('data.id'));

    expect($invitation->organization_id)->toBe($organization->id);
    expect($invitation->status)->toBe('pending');
    expect($invitation->token)->not->toBe('');
    expect(OrganizationMember::query()->where('organization_id', $organization->id)->count())->toBe(1);
});

it('characterizes that the current generic team flow no longer allows creating an additional owner directly', function () {
    [$owner, $organization] = $this->actingAsOwner();

    $response = $this->apiPost('/organizations/current/team', [
        'user' => [
            'name' => 'Novo Proprietario',
            'email' => 'novo-owner@eventovivo.test',
            'phone' => '11999990002',
        ],
        'role_key' => 'partner-owner',
        'send_via_whatsapp' => false,
    ]);

    $this->assertApiValidationError($response, ['role_key']);
});

it('characterizes that signup otp verification creates a brand new organization today', function () {
    $this->seedPermissions();
    Queue::fake();

    $authInstance = WhatsAppInstance::factory()->connected()->create();
    config(['whatsapp.auth.instance_id' => $authInstance->id]);

    $organizationsBefore = Organization::query()->count();

    $requestResponse = $this->apiPost('/auth/register/request-otp', [
        'name' => 'Convite Sem Fluxo',
        'phone' => '(11) 98888-7777',
    ]);

    $sessionToken = $requestResponse->json('data.session_token');
    $code = $requestResponse->json('data.debug_code');

    $verifyResponse = $this->apiPost('/auth/register/verify-otp', [
        'session_token' => $sessionToken,
        'code' => $code,
        'device_name' => 'web-panel',
    ]);

    $this->assertApiSuccess($verifyResponse);

    $user = User::query()->where('phone', '5511988887777')->firstOrFail();

    expect(Organization::query()->count())->toBe($organizationsBefore + 1);
    expect($user->organizationMembers()->count())->toBe(1);
    expect($user->organizationMembers()->first()?->organization_id)->not->toBeNull();
});

it('characterizes that signup otp delivery uses the configured auth sender instance', function () {
    Queue::fake();

    $authInstance = WhatsAppInstance::factory()->connected()->create([
        'name' => 'Auth Sender',
        'is_default' => false,
    ]);

    $otherOrganizationInstance = WhatsAppInstance::factory()->connected()->create([
        'name' => 'Other Organization Sender',
        'is_default' => true,
    ]);

    config(['whatsapp.auth.instance_id' => $authInstance->id]);

    $response = $this->apiPost('/auth/register/request-otp', [
        'name' => 'Sender Scope Test',
        'phone' => '(11) 97777-6666',
    ]);

    $this->assertApiSuccess($response);

    $this->assertDatabaseHas('whatsapp_messages', [
        'instance_id' => $authInstance->id,
        'recipient_phone' => '5511977776666',
    ]);

    $this->assertDatabaseMissing('whatsapp_messages', [
        'instance_id' => $otherOrganizationInstance->id,
        'recipient_phone' => '5511977776666',
    ]);

    Queue::assertPushed(SendWhatsAppMessageJob::class);
});

it('characterizes that inviting an existing platform user by whatsapp reuses the same account across organizations', function () {
    [$ownerA, $organizationA] = $this->actingAsOwner();

    $existingUser = User::factory()->create([
        'name' => 'DJ Reutilizado',
        'email' => 'dj-reutilizado@eventovivo.test',
        'phone' => '5511999123456',
    ]);

    $responseA = $this->apiPost('/organizations/current/team', [
        'user' => [
            'name' => 'DJ Reutilizado',
            'phone' => '(11) 99912-3456',
        ],
        'role_key' => 'event-operator',
        'send_via_whatsapp' => false,
    ]);

    $this->assertApiSuccess($responseA, 201);

    $organizationB = Organization::factory()->create();
    [$ownerB] = $this->actingAsOwner($organizationB);

    $responseB = $this->apiPost('/organizations/current/team', [
        'user' => [
            'name' => 'DJ Reutilizado',
            'phone' => '(11) 99912-3456',
        ],
        'role_key' => 'event-operator',
        'send_via_whatsapp' => false,
    ]);

    $this->assertApiSuccess($responseB, 201);

    expect(User::query()->where('phone', '5511999123456')->count())->toBe(1);

    $existingUser->refresh();

    expect(
        OrganizationMemberInvitation::query()
            ->where('existing_user_id', $existingUser->id)
            ->whereIn('organization_id', [$organizationA->id, $organizationB->id])
            ->count()
    )->toBe(2);
});
