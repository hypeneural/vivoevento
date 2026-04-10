<?php

use App\Modules\EventTeam\Models\EventTeamMember;
use App\Modules\Events\Models\Event;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Users\Models\User;

it('characterizes that an organization event-operator can access media from another event in the same organization', function () {
    [$user, $organization] = $this->actingAsOperator();

    $ownEvent = Event::factory()->create([
        'organization_id' => $organization->id,
        'title' => 'Evento do operador',
    ]);

    $otherEvent = Event::factory()->create([
        'organization_id' => $organization->id,
        'title' => 'Outro evento da organizacao',
    ]);

    EventMedia::factory()->create([
        'event_id' => $otherEvent->id,
    ]);

    $response = $this->apiGet("/events/{$otherEvent->id}/media");

    $this->assertApiSuccess($response);
    expect($response->json('data.0.event_id'))->toBe($otherEvent->id);
    expect($otherEvent->id)->not->toBe($ownEvent->id);
});

it('characterizes that an organization viewer can view media from another event in the same organization', function () {
    [$user, $organization] = $this->actingAsViewer();

    $otherEvent = Event::factory()->create([
        'organization_id' => $organization->id,
        'title' => 'Evento visivel pelo viewer',
    ]);

    EventMedia::factory()->create([
        'event_id' => $otherEvent->id,
    ]);

    $response = $this->apiGet("/events/{$otherEvent->id}/media");

    $this->assertApiSuccess($response);
    expect($response->json('data.0.event_id'))->toBe($otherEvent->id);
});

it('characterizes that an event team assignment now grants event media access only for the assigned event', function () {
    $this->seedPermissions();

    $organization = $this->createOrganization();
    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    EventMedia::factory()->create([
        'event_id' => $event->id,
    ]);

    $user = $this->createUser([
        'email' => 'event-team-only@eventovivo.test',
    ]);
    $user->assignRole('viewer');
    $this->actingAs($user);

    EventTeamMember::query()->create([
        'event_id' => $event->id,
        'user_id' => $user->id,
        'role' => 'viewer',
    ]);

    $response = $this->apiGet("/events/{$event->id}/media");

    $this->assertApiSuccess($response);
    expect($response->json('data.0.event_id'))->toBe($event->id);
});

it('characterizes that an authenticated organization owner can no longer mutate event team membership for a foreign organization event', function () {
    [$owner, $organization] = $this->actingAsOwner();

    $foreignOrganization = $this->createOrganization([
        'trade_name' => 'Outra Organizacao',
    ]);
    $foreignEvent = Event::factory()->create([
        'organization_id' => $foreignOrganization->id,
    ]);

    $invitedUser = $this->createUser([
        'email' => 'foreign-event-team@eventovivo.test',
    ]);

    $response = $this->apiPost("/events/{$foreignEvent->id}/team", [
        'user_id' => $invitedUser->id,
        'role' => 'viewer',
    ]);

    $this->assertApiForbidden($response);

    $this->assertDatabaseMissing('event_team_members', [
        'event_id' => $foreignEvent->id,
        'user_id' => $invitedUser->id,
    ]);
});

it('characterizes that a user with viewer role but no organization membership can no longer list global events without explicit event access', function () {
    $this->seedPermissions();

    $user = $this->createUser([
        'email' => 'viewer-sem-org@eventovivo.test',
    ]);
    $user->assignRole('viewer');
    $this->actingAs($user);

    $organizationA = $this->createOrganization([
        'trade_name' => 'Org A',
    ]);
    $organizationB = $this->createOrganization([
        'trade_name' => 'Org B',
    ]);

    $eventA = Event::factory()->create([
        'organization_id' => $organizationA->id,
        'title' => 'Evento A aberto',
    ]);
    $eventB = Event::factory()->create([
        'organization_id' => $organizationB->id,
        'title' => 'Evento B aberto',
    ]);

    $response = $this->apiGet('/events');

    $this->assertApiSuccess($response);
    expect($response->json('data'))->toBeArray()->toHaveCount(0);
});
