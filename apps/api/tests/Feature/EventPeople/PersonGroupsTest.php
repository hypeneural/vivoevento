<?php

use App\Modules\EventPeople\Models\EventPerson;
use App\Modules\EventPeople\Models\EventPersonMediaStat;
use App\Modules\EventPeople\Models\EventPersonReferencePhoto;
use App\Modules\Events\Models\Event;

it('creates, updates, filters and deletes event people groups with local member stats', function () {
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

    $primaryReference = EventPersonReferencePhoto::factory()->create([
        'event_id' => $event->id,
        'event_person_id' => $bride->id,
        'status' => 'active',
    ]);

    $bride->forceFill([
        'primary_reference_photo_id' => $primaryReference->id,
    ])->save();

    EventPersonMediaStat::query()->create([
        'event_id' => $event->id,
        'event_person_id' => $bride->id,
        'media_count' => 6,
        'solo_media_count' => 2,
        'with_others_media_count' => 4,
        'published_media_count' => 5,
        'pending_media_count' => 1,
        'projected_at' => now(),
    ]);

    EventPersonMediaStat::query()->create([
        'event_id' => $event->id,
        'event_person_id' => $groom->id,
        'media_count' => 4,
        'solo_media_count' => 1,
        'with_others_media_count' => 3,
        'published_media_count' => 3,
        'pending_media_count' => 1,
        'projected_at' => now(),
    ]);

    $createGroupResponse = $this->apiPost("/events/{$event->id}/people/groups", [
        'display_name' => 'Casal principal',
        'group_type' => 'principal',
        'side' => 'neutral',
        'importance_rank' => 100,
        'notes' => 'Grupo mais importante do evento',
    ]);

    $this->assertApiSuccess($createGroupResponse, 201);
    $createGroupResponse->assertJsonPath('data.display_name', 'Casal principal')
        ->assertJsonPath('data.group_type', 'principal')
        ->assertJsonPath('data.stats.member_count', 0);

    $groupId = (int) $createGroupResponse->json('data.id');

    $firstMembershipResponse = $this->apiPost("/events/{$event->id}/people/groups/{$groupId}/members", [
        'event_person_id' => $bride->id,
        'role_label' => 'Noiva',
    ]);

    $this->assertApiSuccess($firstMembershipResponse, 201);
    $firstMembershipResponse->assertJsonPath('data.person.display_name', 'Noiva')
        ->assertJsonPath('data.role_label', 'Noiva');

    $secondMembershipResponse = $this->apiPost("/events/{$event->id}/people/groups/{$groupId}/members", [
        'event_person_id' => $groom->id,
        'role_label' => 'Noivo',
    ]);

    $this->assertApiSuccess($secondMembershipResponse, 201);

    $listResponse = $this->apiGet("/events/{$event->id}/people/groups");

    $this->assertApiSuccess($listResponse);
    $listResponse->assertJsonPath('data.0.id', $groupId)
        ->assertJsonPath('data.0.stats.member_count', 2)
        ->assertJsonPath('data.0.stats.people_with_primary_photo_count', 1)
        ->assertJsonPath('data.0.stats.media_count', 10)
        ->assertJsonPath('data.0.stats.published_media_count', 8)
        ->assertJsonPath('data.0.memberships.0.person.display_name', 'Noiva');

    $filteredResponse = $this->apiGet("/events/{$event->id}/people/groups?person_id={$bride->id}");

    $this->assertApiSuccess($filteredResponse);
    $filteredResponse->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $groupId);

    $updateResponse = $this->apiPatch("/events/{$event->id}/people/groups/{$groupId}", [
        'display_name' => 'Casal oficial',
        'notes' => 'Grupo usado pela operacao',
    ]);

    $this->assertApiSuccess($updateResponse);
    $updateResponse->assertJsonPath('data.display_name', 'Casal oficial')
        ->assertJsonPath('data.notes', 'Grupo usado pela operacao');

    $membershipId = (int) $firstMembershipResponse->json('data.id');

    $this->apiDelete("/events/{$event->id}/people/groups/{$groupId}/members/{$membershipId}")
        ->assertNoContent();

    $this->assertDatabaseMissing('event_person_group_memberships', [
        'id' => $membershipId,
    ]);

    $this->apiDelete("/events/{$event->id}/people/groups/{$groupId}")
        ->assertNoContent();

    $this->assertDatabaseMissing('event_person_groups', [
        'id' => $groupId,
    ]);
});

it('applies preset groups for the event type without duplicating the seed set', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'event_type' => 'wedding',
    ]);

    $firstApplyResponse = $this->apiPost("/events/{$event->id}/people/groups/apply-preset");

    $this->assertApiSuccess($firstApplyResponse);
    $firstApplyResponse->assertJsonFragment([
        'slug' => 'couple',
        'display_name' => 'Casal',
        'group_type' => 'principal',
    ])->assertJsonFragment([
        'slug' => 'bride_family',
        'display_name' => 'Familia da noiva',
        'side' => 'bride_side',
    ]);

    $secondApplyResponse = $this->apiPost("/events/{$event->id}/people/groups/apply-preset");

    $this->assertApiSuccess($secondApplyResponse);
    expect(
        \App\Modules\EventPeople\Models\EventPersonGroup::query()
            ->where('event_id', $event->id)
            ->count()
    )->toBe(4);
});

it('rejects duplicate memberships for the same person inside one event group', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'event_type' => 'corporate',
    ]);

    $person = EventPerson::factory()->create([
        'event_id' => $event->id,
        'display_name' => 'Diretor',
    ]);

    $groupResponse = $this->apiPost("/events/{$event->id}/people/groups", [
        'display_name' => 'Lideranca',
        'group_type' => 'corporativo',
    ]);

    $groupId = (int) $groupResponse->json('data.id');

    $this->apiPost("/events/{$event->id}/people/groups/{$groupId}/members", [
        'event_person_id' => $person->id,
    ])->assertCreated();

    $duplicateResponse = $this->apiPost("/events/{$event->id}/people/groups/{$groupId}/members", [
        'event_person_id' => $person->id,
    ]);

    $duplicateResponse->assertStatus(422)
        ->assertJsonValidationErrors(['event_person_id']);
});
