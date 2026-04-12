<?php

use App\Modules\EventPeople\Models\EventPerson;
use App\Modules\EventPeople\Models\EventPersonGroup;
use App\Modules\EventPeople\Models\EventPersonGroupMembership;
use App\Modules\Events\Models\Event;

it('projects coverage targets and alerts for an event', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'event_type' => 'wedding',
    ]);

    $bride = EventPerson::factory()->create([
        'event_id' => $event->id,
        'display_name' => 'Noiva',
        'type' => 'bride',
        'importance_rank' => 100,
    ]);

    $groom = EventPerson::factory()->create([
        'event_id' => $event->id,
        'display_name' => 'Noivo',
        'type' => 'groom',
        'importance_rank' => 100,
    ]);

    $group = EventPersonGroup::factory()->create([
        'event_id' => $event->id,
        'slug' => 'couple',
        'display_name' => 'Casal',
        'group_type' => 'principal',
        'importance_rank' => 100,
        'status' => 'active',
    ]);

    EventPersonGroupMembership::factory()->create([
        'event_id' => $event->id,
        'event_person_group_id' => $group->id,
        'event_person_id' => $bride->id,
        'status' => 'active',
    ]);

    EventPersonGroupMembership::factory()->create([
        'event_id' => $event->id,
        'event_person_group_id' => $group->id,
        'event_person_id' => $groom->id,
        'status' => 'active',
    ]);

    $refreshResponse = $this->apiPost("/events/{$event->id}/people/coverage/refresh");

    $this->assertApiSuccess($refreshResponse);
    $refreshResponse->assertJsonPath('data.summary.missing', 1);
    $refreshResponse->assertJsonPath('data.summary.active_alerts', 1);
    $refreshResponse->assertJsonCount(1, 'data.alerts');

    $target = collect($refreshResponse->json('data.targets'))
        ->firstWhere('key', 'couple_portrait');

    expect($target)->not()->toBeNull();
    expect($target['stat']['coverage_state'])->toBe('missing');

    $alert = collect($refreshResponse->json('data.alerts'))
        ->firstWhere('target.key', 'couple_portrait');

    expect($alert)->not()->toBeNull();
});
