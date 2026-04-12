<?php

use App\Modules\EventPeople\Models\EventPerson;
use App\Modules\EventPeople\Models\EventPersonFaceAssignment;
use App\Modules\EventPeople\Models\EventPersonGroup;
use App\Modules\EventPeople\Models\EventPersonGroupMembership;
use App\Modules\EventPeople\Models\EventPersonRelation;
use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Models\EventMediaFace;
use App\Modules\MediaProcessing\Models\EventMedia;

it('builds relational collections and guest-ready deliveries from local identities', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'event_type' => 'wedding',
    ]);

    $bride = EventPerson::factory()->create([
        'event_id' => $event->id,
        'display_name' => 'Noiva',
        'type' => 'bride',
        'importance_rank' => 100,
        'status' => 'active',
    ]);

    $groom = EventPerson::factory()->create([
        'event_id' => $event->id,
        'display_name' => 'Noivo',
        'type' => 'groom',
        'importance_rank' => 100,
        'status' => 'active',
    ]);

    $motherBride = EventPerson::factory()->create([
        'event_id' => $event->id,
        'display_name' => 'Mae da noiva',
        'type' => 'mother',
        'side' => 'bride_side',
        'importance_rank' => 90,
        'status' => 'active',
    ]);

    $coupleGroup = EventPersonGroup::factory()->create([
        'event_id' => $event->id,
        'slug' => 'couple',
        'display_name' => 'Casal',
        'group_type' => 'principal',
        'importance_rank' => 100,
        'status' => 'active',
    ]);

    $familyGroup = EventPersonGroup::factory()->create([
        'event_id' => $event->id,
        'slug' => 'bride_family',
        'display_name' => 'Familia da noiva',
        'group_type' => 'familia',
        'importance_rank' => 95,
        'status' => 'active',
    ]);

    foreach ([
        [$coupleGroup->id, $bride->id, 'Noiva'],
        [$coupleGroup->id, $groom->id, 'Noivo'],
        [$familyGroup->id, $bride->id, 'Noiva'],
        [$familyGroup->id, $motherBride->id, 'Mae da noiva'],
    ] as [$groupId, $personId, $roleLabel]) {
        EventPersonGroupMembership::factory()->create([
            'event_id' => $event->id,
            'event_person_group_id' => $groupId,
            'event_person_id' => $personId,
            'role_label' => $roleLabel,
            'status' => 'active',
        ]);
    }

    EventPersonRelation::factory()->create([
        'event_id' => $event->id,
        'person_a_id' => $bride->id,
        'person_b_id' => $groom->id,
        'relation_type' => 'spouse_of',
        'person_pair_key' => "{$bride->id}:{$groom->id}",
        'is_primary' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $publishedCoupleMedia = EventMedia::factory()->published()->create(['event_id' => $event->id]);
    $internalCoupleMedia = EventMedia::factory()->approved()->create(['event_id' => $event->id]);
    $publishedFamilyMedia = EventMedia::factory()->published()->create(['event_id' => $event->id]);

    foreach ([
        [$publishedCoupleMedia, $bride],
        [$publishedCoupleMedia, $groom],
        [$internalCoupleMedia, $bride],
        [$internalCoupleMedia, $groom],
        [$publishedFamilyMedia, $bride],
        [$publishedFamilyMedia, $motherBride],
    ] as [$media, $person]) {
        $face = EventMediaFace::factory()->create([
            'event_id' => $event->id,
            'event_media_id' => $media->id,
        ]);

        EventPersonFaceAssignment::factory()->create([
            'event_id' => $event->id,
            'event_person_id' => $person->id,
            'event_media_face_id' => $face->id,
            'status' => 'confirmed',
        ]);
    }

    $refreshResponse = $this->apiPost("/events/{$event->id}/people/relational-collections/refresh");

    $this->assertApiSuccess($refreshResponse);
    expect((int) $refreshResponse->json('data.summary.total_collections'))->toBeGreaterThanOrEqual(5);
    expect((int) $refreshResponse->json('data.summary.public_ready_collections'))->toBeGreaterThanOrEqual(2);
    expect((int) $refreshResponse->json('data.summary.must_have_deliveries'))->toBeGreaterThanOrEqual(2);

    $pairCollection = collect($refreshResponse->json('data.collections'))
        ->firstWhere('collection_type', 'pair_best_of');

    expect($pairCollection)->not()->toBeNull();
    expect($pairCollection['display_name'])->toBe('Noiva + Noivo');
    expect($pairCollection['visibility'])->toBe('internal');
    expect($pairCollection['item_count'])->toBe(2);

    $personCollection = collect($refreshResponse->json('data.collections'))
        ->firstWhere('collection_key', "person-best-of:{$bride->id}");

    expect($personCollection)->not()->toBeNull();
    expect($personCollection['display_name'])->toBe('Melhores de Noiva');
    expect($personCollection['visibility'])->toBe('internal');
    expect($personCollection['item_count'])->toBe(3);

    $groupCollection = collect($refreshResponse->json('data.collections'))
        ->firstWhere('collection_key', "group-best-of:{$familyGroup->id}");

    expect($groupCollection)->not()->toBeNull();
    expect($groupCollection['display_name'])->toBe('Familia da noiva');
    expect($groupCollection['visibility'])->toBe('internal');
    expect($groupCollection['item_count'])->toBe(1);

    $mustHaveCollection = collect($refreshResponse->json('data.collections'))
        ->firstWhere('collection_key', 'must-have:couple_portrait');

    expect($mustHaveCollection)->not()->toBeNull();
    expect($mustHaveCollection['visibility'])->toBe('public_ready');
    expect($mustHaveCollection['published_item_count'])->toBe(1);
    expect($mustHaveCollection['share_token'])->not()->toBeNull();
    expect($mustHaveCollection['public_url'])->toContain('/momentos/');
    expect($mustHaveCollection['public_api_url'])->toContain('/api/v1/public/people-collections/');

    $familyMoment = collect($refreshResponse->json('data.collections'))
        ->firstWhere('collection_type', 'family_moment');

    expect($familyMoment)->not()->toBeNull();
    expect($familyMoment['display_name'])->toBe('Momentos de Familia da noiva');

    $listResponse = $this->apiGet("/events/{$event->id}/people/relational-collections");

    $this->assertApiSuccess($listResponse);
    expect($listResponse->json('data.summary.total_collections'))->toBe($refreshResponse->json('data.summary.total_collections'));

    $publicResponse = $this->apiGet("/public/people-collections/{$mustHaveCollection['share_token']}");

    $this->assertApiSuccess($publicResponse);
    expect($publicResponse->json('data.event.title'))->toBe($event->title);
    expect($publicResponse->json('data.collection.display_name'))->toBe('Casal junto');
    expect($publicResponse->json('data.collection.item_count'))->toBe(1);
    expect($publicResponse->json('data.collection.items.0.event_media_id'))->toBe($publishedCoupleMedia->id);
    expect($publicResponse->json('data.collection.items.0.is_published'))->toBeTrue();

    \App\Modules\EventPeople\Models\EventRelationalCollection::query()
        ->where('collection_key', 'must-have:couple_portrait')
        ->update(['visibility' => 'internal']);

    $this->apiGet("/public/people-collections/{$mustHaveCollection['share_token']}")
        ->assertNotFound();
});
