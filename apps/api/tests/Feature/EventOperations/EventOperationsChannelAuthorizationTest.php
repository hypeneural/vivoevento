<?php

use App\Modules\Events\Models\Event;
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

it('allows private operations channel authentication for users with event access and operations view', function () {
    [$user, $organization] = $this->actingAsOwner();

    $domainEvent = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->post('/broadcasting/auth', [
        'channel_name' => "private-event.{$domainEvent->id}.operations",
        'socket_id' => '1234.5678',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['auth']);
});

it('rejects operations channel authentication when the user lacks operations view permission', function () {
    [$user, $organization] = $this->actingAsViewer();

    $domainEvent = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->post('/broadcasting/auth', [
        'channel_name' => "private-event.{$domainEvent->id}.operations",
        'socket_id' => '1234.5678',
    ]);

    $response->assertForbidden();
});

it('rejects operations channel authentication for users outside the event organization', function () {
    [$user, $organization] = $this->actingAsOwner();

    $otherOrganization = $this->createOrganization();

    $domainEvent = Event::factory()->active()->create([
        'organization_id' => $otherOrganization->id,
    ]);

    $response = $this->post('/broadcasting/auth', [
        'channel_name' => "private-event.{$domainEvent->id}.operations",
        'socket_id' => '1234.5678',
    ]);

    $response->assertForbidden();
});
