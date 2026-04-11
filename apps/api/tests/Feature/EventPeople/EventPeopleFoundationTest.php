<?php

use App\Modules\EventPeople\Enums\EventPersonAssignmentStatus;
use App\Modules\EventPeople\Enums\EventPersonReviewQueueStatus;
use App\Modules\EventPeople\Enums\EventPersonReviewQueueType;
use App\Modules\EventPeople\Models\EventPerson;
use App\Modules\EventPeople\Models\EventPersonFaceAssignment;
use App\Modules\EventPeople\Models\EventPersonMediaStat;
use App\Modules\EventPeople\Models\EventPersonReviewQueueItem;
use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Models\EventMediaFace;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Database\QueryException;

it('lists event people from local read models without touching face search providers', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create(['organization_id' => $organization->id]);
    $otherEvent = Event::factory()->create(['organization_id' => $organization->id]);

    $person = EventPerson::factory()->create([
        'event_id' => $event->id,
        'display_name' => 'Maria Silva',
        'slug' => 'maria-silva',
        'importance_rank' => 20,
    ]);

    EventPerson::factory()->create([
        'event_id' => $otherEvent->id,
        'display_name' => 'Pessoa de outro evento',
    ]);

    EventPersonMediaStat::query()->create([
        'event_id' => $event->id,
        'event_person_id' => $person->id,
        'media_count' => 5,
        'solo_media_count' => 2,
        'with_others_media_count' => 3,
        'published_media_count' => 4,
        'pending_media_count' => 1,
        'projected_at' => now(),
    ]);

    $response = $this->apiGet("/events/{$event->id}/people?search=maria");

    $this->assertApiPaginated($response);
    $response->assertJsonPath('data.0.display_name', 'Maria Silva')
        ->assertJsonPath('data.0.stats.0.media_count', 5)
        ->assertJsonCount(1, 'data');
});

it('shows a person only inside the requested event scope', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create(['organization_id' => $organization->id]);
    $otherEvent = Event::factory()->create(['organization_id' => $organization->id]);

    $person = EventPerson::factory()->create([
        'event_id' => $event->id,
        'display_name' => 'Noiva',
    ]);

    $this->assertApiSuccess($this->apiGet("/events/{$event->id}/people/{$person->id}"))
        ->assertDatabaseHas('event_people', [
            'id' => $person->id,
            'event_id' => $event->id,
        ]);

    $this->apiGet("/events/{$otherEvent->id}/people/{$person->id}")
        ->assertNotFound();
});

it('keeps the review queue behind moderation permission', function () {
    [$viewer, $organization] = $this->actingAsViewer();

    $event = Event::factory()->create(['organization_id' => $organization->id]);

    EventPersonReviewQueueItem::query()->create([
        'event_id' => $event->id,
        'queue_key' => 'unknown-face:1',
        'type' => EventPersonReviewQueueType::UnknownPerson->value,
        'status' => EventPersonReviewQueueStatus::Pending->value,
        'priority' => 90,
        'payload' => ['label' => 'Quem e esta pessoa?'],
        'last_signal_at' => now(),
    ]);

    $this->apiGet("/events/{$event->id}/people/review-queue")
        ->assertForbidden();

    $this->actingAsOwner($organization);

    $response = $this->apiGet("/events/{$event->id}/people/review-queue");

    $this->assertApiPaginated($response);
    $response->assertJsonPath('data.0.queue_key', 'unknown-face:1')
        ->assertJsonPath('data.0.status', 'pending');
});

it('returns media faces with current person assignments for the overlay contract', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create(['organization_id' => $organization->id]);
    $media = EventMedia::factory()->create(['event_id' => $event->id]);
    $face = EventMediaFace::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'face_index' => 2,
        'bbox_x' => 20,
        'bbox_y' => 30,
        'bbox_w' => 120,
        'bbox_h' => 140,
    ]);
    $person = EventPerson::factory()->create([
        'event_id' => $event->id,
        'display_name' => 'Mae da noiva',
    ]);

    EventPersonFaceAssignment::query()->create([
        'event_id' => $event->id,
        'event_person_id' => $person->id,
        'event_media_face_id' => $face->id,
        'source' => 'manual_confirmed',
        'confidence' => 1,
        'status' => EventPersonAssignmentStatus::Confirmed->value,
        'reviewed_by' => $user->id,
        'reviewed_at' => now(),
    ]);

    $response = $this->apiGet("/events/{$event->id}/media/{$media->id}/people");

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.0.face_index', 2)
        ->assertJsonPath('data.0.bbox.x', 20)
        ->assertJsonPath('data.0.assignments.0.person.display_name', 'Mae da noiva');
});

it('enforces a single confirmed person assignment per detected face', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create(['organization_id' => $organization->id]);
    $media = EventMedia::factory()->create(['event_id' => $event->id]);
    $face = EventMediaFace::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
    ]);

    $firstPerson = EventPerson::factory()->create(['event_id' => $event->id]);
    $secondPerson = EventPerson::factory()->create(['event_id' => $event->id]);

    EventPersonFaceAssignment::query()->create([
        'event_id' => $event->id,
        'event_person_id' => $firstPerson->id,
        'event_media_face_id' => $face->id,
        'source' => 'manual_confirmed',
        'status' => EventPersonAssignmentStatus::Confirmed->value,
    ]);

    expect(fn () => EventPersonFaceAssignment::query()->create([
        'event_id' => $event->id,
        'event_person_id' => $secondPerson->id,
        'event_media_face_id' => $face->id,
        'source' => 'manual_corrected',
        'status' => EventPersonAssignmentStatus::Confirmed->value,
    ]))->toThrow(QueryException::class);
});
