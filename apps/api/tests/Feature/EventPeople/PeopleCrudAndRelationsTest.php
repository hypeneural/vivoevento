<?php

use App\Modules\EventPeople\Models\EventPerson;
use App\Modules\EventPeople\Models\EventPersonRelation;
use App\Modules\Events\Models\Event;

it('creates and updates people manually outside the guided review flow', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'event_type' => 'wedding',
    ]);

    $createResponse = $this->apiPost("/events/{$event->id}/people", [
        'display_name' => 'Mae da noiva',
        'type' => 'mother',
        'side' => 'bride_side',
        'importance_rank' => 90,
        'notes' => 'Pessoa importante do evento',
    ]);

    $this->assertApiSuccess($createResponse, 201);
    $createResponse->assertJsonPath('data.display_name', 'Mae da noiva')
        ->assertJsonPath('data.type', 'mother')
        ->assertJsonPath('data.side', 'bride_side')
        ->assertJsonPath('data.status', 'active');

    $personId = (int) $createResponse->json('data.id');

    $updateResponse = $this->apiPatch("/events/{$event->id}/people/{$personId}", [
        'display_name' => 'Mae da noiva ajustada',
        'importance_rank' => 95,
        'status' => 'draft',
    ]);

    $this->assertApiSuccess($updateResponse);
    $updateResponse->assertJsonPath('data.display_name', 'Mae da noiva ajustada')
        ->assertJsonPath('data.importance_rank', 95)
        ->assertJsonPath('data.status', 'draft');

    $this->assertDatabaseHas('event_people', [
        'id' => $personId,
        'event_id' => $event->id,
        'display_name' => 'Mae da noiva ajustada',
        'status' => 'draft',
    ]);
});

it('returns presets and allows creating, updating and deleting manual relations', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'event_type' => 'wedding',
    ]);

    $bride = EventPerson::factory()->create([
        'event_id' => $event->id,
        'display_name' => 'Noiva',
        'type' => 'bride',
    ]);

    $groom = EventPerson::factory()->create([
        'event_id' => $event->id,
        'display_name' => 'Noivo',
        'type' => 'groom',
    ]);

    $presetsResponse = $this->apiGet("/events/{$event->id}/people/presets");

    $this->assertApiSuccess($presetsResponse);
    $presetsResponse->assertJsonPath('data.event_type', 'wedding')
        ->assertJsonFragment([
            'key' => 'bride',
            'label' => 'Noiva',
            'type' => 'bride',
        ])
        ->assertJsonFragment([
            'type' => 'spouse_of',
            'label' => 'Conjuge de',
            'directionality' => 'undirected',
        ]);

    $createRelationResponse = $this->apiPost("/events/{$event->id}/people/relations", [
        'person_a_id' => $bride->id,
        'person_b_id' => $groom->id,
        'relation_type' => 'spouse_of',
        'directionality' => 'undirected',
        'is_primary' => true,
        'notes' => 'Casal principal',
    ]);

    $this->assertApiSuccess($createRelationResponse, 201);
    $createRelationResponse->assertJsonPath('data.relation_type', 'spouse_of')
        ->assertJsonPath('data.is_primary', true)
        ->assertJsonPath('data.person_a.display_name', 'Noiva')
        ->assertJsonPath('data.person_b.display_name', 'Noivo');

    $relationId = (int) $createRelationResponse->json('data.id');

    $showBrideResponse = $this->apiGet("/events/{$event->id}/people/{$bride->id}");

    $this->assertApiSuccess($showBrideResponse);
    $showBrideResponse->assertJsonPath('data.relations.0.other_person.display_name', 'Noivo')
        ->assertJsonPath('data.relations.0.relation_type', 'spouse_of');

    $updateRelationResponse = $this->apiPatch("/events/{$event->id}/people/relations/{$relationId}", [
        'notes' => 'Casal confirmado no cadastro manual',
        'is_primary' => false,
    ]);

    $this->assertApiSuccess($updateRelationResponse);
    $updateRelationResponse->assertJsonPath('data.notes', 'Casal confirmado no cadastro manual')
        ->assertJsonPath('data.is_primary', false);

    $this->apiDelete("/events/{$event->id}/people/relations/{$relationId}")
        ->assertNoContent();

    $this->assertDatabaseMissing('event_person_relations', [
        'id' => $relationId,
    ]);
});

