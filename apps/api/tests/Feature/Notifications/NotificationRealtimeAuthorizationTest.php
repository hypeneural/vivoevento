<?php

use App\Modules\Organizations\Models\OrganizationMember;
use Illuminate\Support\Facades\Broadcast;

beforeEach(function () {
    config()->set('broadcasting.default', 'reverb');
    config()->set('broadcasting.connections.reverb.key', 'test-key');
    config()->set('broadcasting.connections.reverb.secret', 'test-secret');
    config()->set('broadcasting.connections.reverb.app_id', 'test-app');
    config()->set('broadcasting.connections.reverb.options.host', '127.0.0.1');
    config()->set('broadcasting.connections.reverb.options.port', 8080);
    config()->set('broadcasting.connections.reverb.options.scheme', 'http');
    config()->set('broadcasting.connections.reverb.options.useTLS', false);

    Broadcast::forgetDrivers();

    require base_path('routes/channels.php');
});

it('allows a user with notifications permission to authenticate their own private notifications channel', function () {
    [$user] = $this->actingAsOwner();

    $response = $this->post('/broadcasting/auth', [
        'channel_name' => "private-user.{$user->id}.notifications",
        'socket_id' => '1234.5678',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['auth']);
});

it('rejects private notifications channel authentication when the channel belongs to another user', function () {
    [$user] = $this->actingAsOwner();
    $otherUser = $this->createUser();

    $response = $this->post('/broadcasting/auth', [
        'channel_name' => "private-user.{$otherUser->id}.notifications",
        'socket_id' => '1234.5678',
    ]);

    $response->assertForbidden();
});

it('rejects private notifications channel authentication for finance users while the role lacks notifications permission', function () {
    $this->seedPermissions();

    $organization = $this->createOrganization();
    $user = $this->createUser();

    OrganizationMember::create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role_key' => 'financeiro',
        'is_owner' => false,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $user->assignRole('financeiro');
    $this->actingAs($user);

    $response = $this->post('/broadcasting/auth', [
        'channel_name' => "private-user.{$user->id}.notifications",
        'socket_id' => '1234.5678',
    ]);

    $response->assertForbidden();
});
