<?php

use App\Modules\Events\Models\Event;
use App\Modules\EventTeam\Models\EventTeamMember;
use App\Modules\MediaProcessing\Models\EventMedia;

it('allows an event-team moderator to moderate only the assigned event without organization-wide membership', function () {
    $this->seedPermissions();

    $organization = $this->createOrganization();
    $allowedEvent = Event::factory()->create(['organization_id' => $organization->id]);
    $forbiddenEvent = Event::factory()->create(['organization_id' => $organization->id]);

    $allowedMedia = EventMedia::factory()->create(['event_id' => $allowedEvent->id]);
    $forbiddenMedia = EventMedia::factory()->create(['event_id' => $forbiddenEvent->id]);

    $user = $this->createUser(['email' => 'event-moderator@eventovivo.test']);
    $user->assignRole('viewer');
    $this->actingAs($user);

    EventTeamMember::query()->create([
        'event_id' => $allowedEvent->id,
        'user_id' => $user->id,
        'role' => 'moderator',
    ]);

    $this->assertApiSuccess(
        $this->apiPost("/media/{$allowedMedia->id}/approve"),
    );

    $this->assertApiForbidden(
        $this->apiPost("/media/{$forbiddenMedia->id}/approve"),
    );
});

it('allows an event-team media viewer to view only the assigned event media feed', function () {
    $this->seedPermissions();

    $organization = $this->createOrganization();
    $allowedEvent = Event::factory()->create(['organization_id' => $organization->id]);
    $forbiddenEvent = Event::factory()->create(['organization_id' => $organization->id]);

    EventMedia::factory()->create(['event_id' => $allowedEvent->id]);
    EventMedia::factory()->create(['event_id' => $forbiddenEvent->id]);

    $user = $this->createUser(['email' => 'event-viewer@eventovivo.test']);
    $user->assignRole('viewer');
    $this->actingAs($user);

    EventTeamMember::query()->create([
        'event_id' => $allowedEvent->id,
        'user_id' => $user->id,
        'role' => 'viewer',
    ]);

    $allowedResponse = $this->apiGet("/events/{$allowedEvent->id}/media");
    $forbiddenResponse = $this->apiGet("/events/{$forbiddenEvent->id}/media");

    $this->assertApiSuccess($allowedResponse);
    $this->assertApiForbidden($forbiddenResponse);
});

it('prevents event-scoped users from listing unrelated events in the global events index', function () {
    $this->seedPermissions();

    $organizationA = $this->createOrganization(['trade_name' => 'Org A']);
    $organizationB = $this->createOrganization(['trade_name' => 'Org B']);

    $allowedEvent = Event::factory()->create([
        'organization_id' => $organizationA->id,
        'title' => 'Evento Permitido',
    ]);
    $forbiddenEvent = Event::factory()->create([
        'organization_id' => $organizationB->id,
        'title' => 'Evento Bloqueado',
    ]);

    $user = $this->createUser(['email' => 'event-index@eventovivo.test']);
    $user->assignRole('viewer');
    $user->update([
        'preferences' => [
            'active_context' => [
                'type' => 'event',
                'event_id' => $allowedEvent->id,
                'organization_id' => $organizationA->id,
            ],
        ],
    ]);
    $this->actingAs($user);

    EventTeamMember::query()->create([
        'event_id' => $allowedEvent->id,
        'user_id' => $user->id,
        'role' => 'viewer',
    ]);

    $response = $this->apiGet('/events');

    $this->assertApiSuccess($response);
    expect(collect($response->json('data'))->pluck('id')->all())
        ->toContain($allowedEvent->id)
        ->not->toContain($forbiddenEvent->id);
});

it('prevents event-scoped users from consuming organization-wide moderation feeds and channels')->todo();

it('restricts event team management to authorized organization owners managers or event managers for the same event')->todo();

it('keeps organization-level staff with org-wide access while preserving per-user audit attribution')->todo();
