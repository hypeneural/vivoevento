<?php

use App\Modules\EventPeople\Enums\EventPersonAssignmentStatus;
use App\Modules\EventPeople\Enums\EventPersonReviewQueueStatus;
use App\Modules\EventPeople\Enums\EventPersonReviewQueueType;
use App\Modules\EventPeople\Models\EventPerson;
use App\Modules\EventPeople\Models\EventPersonFaceAssignment;
use App\Modules\EventPeople\Models\EventPersonReviewQueueItem;
use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Models\EventMediaFace;
use App\Modules\MediaProcessing\Models\EventMedia;

it('projects unknown searchable faces into the review queue automatically', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create(['organization_id' => $organization->id]);
    $media = EventMedia::factory()->create(['event_id' => $event->id]);
    $face = EventMediaFace::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'face_index' => 4,
        'quality_tier' => 'search_priority',
        'quality_score' => 0.91,
        'searchable' => true,
    ]);

    $response = $this->apiGet("/events/{$event->id}/people/review-queue");

    $this->assertApiPaginated($response);
    $response->assertJsonPath('data.0.queue_key', 'unknown-face:' . $face->id)
        ->assertJsonPath('data.0.type', EventPersonReviewQueueType::UnknownPerson->value)
        ->assertJsonPath('data.0.status', EventPersonReviewQueueStatus::Pending->value)
        ->assertJsonPath('data.0.face.face_index', 4);

    $overlay = $this->apiGet("/events/{$event->id}/media/{$media->id}/people");

    $this->assertApiSuccess($overlay);
    $overlay->assertJsonPath('data.0.review_item.queue_key', 'unknown-face:' . $face->id)
        ->assertJsonPath('data.0.current_assignment', null);
});

it('confirms a review item into an existing person and resolves the queue locally', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create(['organization_id' => $organization->id]);
    $media = EventMedia::factory()->create(['event_id' => $event->id]);
    $face = EventMediaFace::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'searchable' => true,
    ]);
    $person = EventPerson::factory()->create([
        'event_id' => $event->id,
        'display_name' => 'Maria da Cerimonia',
    ]);

    $reviewItemId = $this->apiGet("/events/{$event->id}/people/review-queue")
        ->json('data.0.id');

    $response = $this->apiPost("/events/{$event->id}/people/review-queue/{$reviewItemId}/confirm", [
        'person_id' => $person->id,
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.person.display_name', 'Maria da Cerimonia')
        ->assertJsonPath('data.review_item.status', EventPersonReviewQueueStatus::Resolved->value)
        ->assertJsonPath('data.face.current_assignment.person.display_name', 'Maria da Cerimonia');

    $this->assertDatabaseHas('event_person_face_assignments', [
        'event_id' => $event->id,
        'event_person_id' => $person->id,
        'event_media_face_id' => $face->id,
        'status' => EventPersonAssignmentStatus::Confirmed->value,
    ]);

    $this->assertDatabaseHas('event_person_review_queue', [
        'id' => $reviewItemId,
        'status' => EventPersonReviewQueueStatus::Resolved->value,
        'event_person_id' => $person->id,
    ]);
});

it('creates a new person inline when confirming a face', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create(['organization_id' => $organization->id]);
    $media = EventMedia::factory()->create(['event_id' => $event->id]);
    EventMediaFace::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'searchable' => true,
    ]);

    $reviewItemId = $this->apiGet("/events/{$event->id}/people/review-queue")
        ->json('data.0.id');

    $response = $this->apiPost("/events/{$event->id}/people/review-queue/{$reviewItemId}/confirm", [
        'person' => [
            'display_name' => 'Noiva Principal',
            'type' => 'bride',
            'side' => 'neutral',
            'importance_rank' => 100,
        ],
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.person.display_name', 'Noiva Principal')
        ->assertJsonPath('data.person.type', 'bride');

    $this->assertDatabaseHas('event_people', [
        'event_id' => $event->id,
        'display_name' => 'Noiva Principal',
        'slug' => 'noiva-principal',
        'type' => 'bride',
    ]);
});

it('keeps the confirmation idempotent and allows moving the face to another person', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create(['organization_id' => $organization->id]);
    $media = EventMedia::factory()->create(['event_id' => $event->id]);
    $face = EventMediaFace::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'searchable' => true,
    ]);
    $firstPerson = EventPerson::factory()->create(['event_id' => $event->id, 'display_name' => 'Primeira Pessoa']);
    $secondPerson = EventPerson::factory()->create(['event_id' => $event->id, 'display_name' => 'Segunda Pessoa']);

    $reviewItemId = $this->apiGet("/events/{$event->id}/people/review-queue")->json('data.0.id');

    $this->apiPost("/events/{$event->id}/people/review-queue/{$reviewItemId}/confirm", [
        'person_id' => $firstPerson->id,
    ])->assertOk();

    $this->apiPost("/events/{$event->id}/people/review-queue/{$reviewItemId}/confirm", [
        'person_id' => $firstPerson->id,
    ])->assertOk();

    expect(EventPersonFaceAssignment::query()
        ->where('event_media_face_id', $face->id)
        ->where('status', EventPersonAssignmentStatus::Confirmed->value)
        ->count())->toBe(1);

    $moveResponse = $this->apiPost("/events/{$event->id}/people/review-queue/{$reviewItemId}/confirm", [
        'person_id' => $secondPerson->id,
    ]);

    $this->assertApiSuccess($moveResponse);
    $moveResponse->assertJsonPath('data.person.display_name', 'Segunda Pessoa')
        ->assertJsonPath('data.face.current_assignment.person.display_name', 'Segunda Pessoa');

    expect(EventPersonFaceAssignment::query()
        ->where('event_media_face_id', $face->id)
        ->count())->toBe(1);

    $this->assertDatabaseHas('event_person_face_assignments', [
        'event_media_face_id' => $face->id,
        'event_person_id' => $secondPerson->id,
        'status' => EventPersonAssignmentStatus::Confirmed->value,
    ]);
});

it('ignores and rejects review items without recreating them as pending on the next read', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create(['organization_id' => $organization->id]);
    $media = EventMedia::factory()->create(['event_id' => $event->id]);

    $firstFace = EventMediaFace::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'face_index' => 1,
        'searchable' => true,
    ]);

    $secondFace = EventMediaFace::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'face_index' => 2,
        'searchable' => true,
    ]);

    $items = collect($this->apiGet("/events/{$event->id}/people/review-queue")->json('data'));
    $ignoreId = $items->firstWhere('event_media_face_id', $firstFace->id)['id'];
    $rejectId = $items->firstWhere('event_media_face_id', $secondFace->id)['id'];

    $this->apiPost("/events/{$event->id}/people/review-queue/{$ignoreId}/ignore")
        ->assertOk()
        ->assertJsonPath('data.review_item.status', EventPersonReviewQueueStatus::Ignored->value);

    $this->apiPost("/events/{$event->id}/people/review-queue/{$rejectId}/reject")
        ->assertOk()
        ->assertJsonPath('data.review_item.status', EventPersonReviewQueueStatus::Ignored->value)
        ->assertJsonPath('data.review_item.payload.resolution', 'rejected');

    $fresh = $this->apiGet("/events/{$event->id}/people/review-queue");

    $this->assertApiPaginated($fresh);
    $fresh->assertJsonFragment([
        'id' => $ignoreId,
        'status' => EventPersonReviewQueueStatus::Ignored->value,
    ])->assertJsonFragment([
        'id' => $rejectId,
        'status' => EventPersonReviewQueueStatus::Ignored->value,
    ]);
});

it('splits a confirmed face back into pending review', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create(['organization_id' => $organization->id]);
    $media = EventMedia::factory()->create(['event_id' => $event->id]);
    $face = EventMediaFace::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'searchable' => true,
    ]);
    $person = EventPerson::factory()->create([
        'event_id' => $event->id,
        'display_name' => 'Pessoa Associada',
    ]);

    $reviewItemId = $this->apiGet("/events/{$event->id}/people/review-queue")->json('data.0.id');

    $this->apiPost("/events/{$event->id}/people/review-queue/{$reviewItemId}/confirm", [
        'person_id' => $person->id,
    ])->assertOk();

    $response = $this->apiPost("/events/{$event->id}/people/review-queue/{$reviewItemId}/split");

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.review_item.status', EventPersonReviewQueueStatus::Pending->value)
        ->assertJsonPath('data.face.current_assignment', null);

    $this->assertDatabaseHas('event_person_face_assignments', [
        'event_media_face_id' => $face->id,
        'event_person_id' => $person->id,
        'status' => EventPersonAssignmentStatus::Rejected->value,
    ]);
});

it('projects the identity conflict automatically and merges one event person into another', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create(['organization_id' => $organization->id]);
    $sourcePerson = EventPerson::factory()->create([
        'event_id' => $event->id,
        'display_name' => 'Pessoa Origem',
    ]);
    $targetPerson = EventPerson::factory()->create([
        'event_id' => $event->id,
        'display_name' => 'Pessoa Destino',
    ]);
    $media = EventMedia::factory()->create(['event_id' => $event->id]);
    $face = EventMediaFace::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'searchable' => true,
    ]);

    EventPersonFaceAssignment::query()->create([
        'event_id' => $event->id,
        'event_person_id' => $sourcePerson->id,
        'event_media_face_id' => $face->id,
        'source' => 'manual_confirmed',
        'status' => EventPersonAssignmentStatus::Confirmed->value,
        'reviewed_by' => $user->id,
        'reviewed_at' => now(),
    ]);

    EventPersonFaceAssignment::query()->create([
        'event_id' => $event->id,
        'event_person_id' => $targetPerson->id,
        'event_media_face_id' => $face->id,
        'source' => 'manual_corrected',
        'status' => EventPersonAssignmentStatus::Rejected->value,
        'reviewed_by' => $user->id,
        'reviewed_at' => now(),
    ]);

    $reviewQueueResponse = $this->apiGet("/events/{$event->id}/people/review-queue");

    $this->assertApiPaginated($reviewQueueResponse);
    $reviewQueueResponse->assertJsonFragment([
        'queue_key' => 'identity-conflict:' . $face->id,
        'type' => EventPersonReviewQueueType::IdentityConflict->value,
        'status' => EventPersonReviewQueueStatus::Conflict->value,
    ]);

    $reviewItemId = collect($reviewQueueResponse->json('data'))
        ->firstWhere('queue_key', 'identity-conflict:' . $face->id)['id'];

    $response = $this->apiPost("/events/{$event->id}/people/review-queue/{$reviewItemId}/merge", [
        'source_person_id' => $sourcePerson->id,
        'target_person_id' => $targetPerson->id,
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.source_person.status', 'hidden')
        ->assertJsonPath('data.target_person.display_name', 'Pessoa Destino')
        ->assertJsonPath('data.review_item.status', EventPersonReviewQueueStatus::Resolved->value);

    $this->assertDatabaseHas('event_person_face_assignments', [
        'event_media_face_id' => $face->id,
        'event_person_id' => $targetPerson->id,
        'status' => EventPersonAssignmentStatus::Confirmed->value,
    ]);

    $this->assertDatabaseHas('event_person_review_queue', [
        'queue_key' => 'identity-conflict:' . $face->id,
        'status' => EventPersonReviewQueueStatus::Resolved->value,
        'event_person_id' => $targetPerson->id,
    ]);
});

it('keeps review queue writes scoped to the requested event', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create(['organization_id' => $organization->id]);
    $otherEvent = Event::factory()->create(['organization_id' => $organization->id]);
    $media = EventMedia::factory()->create(['event_id' => $event->id]);
    EventMediaFace::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'searchable' => true,
    ]);
    $person = EventPerson::factory()->create(['event_id' => $event->id]);

    $reviewItemId = $this->apiGet("/events/{$event->id}/people/review-queue")->json('data.0.id');

    $this->apiPost("/events/{$otherEvent->id}/people/review-queue/{$reviewItemId}/confirm", [
        'person_id' => $person->id,
    ])->assertNotFound();
});
