<?php

use App\Modules\EventPeople\Enums\EventPersonAssignmentStatus;
use App\Modules\EventPeople\Enums\EventPersonRepresentativeSyncStatus;
use App\Modules\EventPeople\Jobs\SyncEventPersonRepresentativeFacesJob;
use App\Modules\EventPeople\Models\EventPerson;
use App\Modules\EventPeople\Models\EventPersonFaceAssignment;
use App\Modules\EventPeople\Models\EventPersonRepresentativeFace;
use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\FaceSearch\Models\EventMediaFace;
use App\Modules\FaceSearch\Models\FaceSearchProviderRecord;
use App\Modules\FaceSearch\Services\AwsRekognitionFaceSearchBackend;
use App\Modules\MediaProcessing\Models\EventMedia;

it('marks representatives as skipped when aws sync is not enabled for the event', function () {
    $event = Event::factory()->create();
    EventFaceSearchSetting::factory()->create([
        'event_id' => $event->id,
        'enabled' => false,
        'recognition_enabled' => false,
        'search_backend_key' => 'local_pgvector',
    ]);

    $person = EventPerson::factory()->create(['event_id' => $event->id]);
    $media = EventMedia::factory()->create(['event_id' => $event->id]);
    $face = EventMediaFace::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
    ]);

    EventPersonFaceAssignment::factory()->create([
        'event_id' => $event->id,
        'event_person_id' => $person->id,
        'event_media_face_id' => $face->id,
        'status' => EventPersonAssignmentStatus::Confirmed->value,
    ]);

    (new SyncEventPersonRepresentativeFacesJob($event->id, $person->id))->handle();

    $representative = EventPersonRepresentativeFace::query()->firstOrFail();

    expect($representative->sync_status)->toBe(EventPersonRepresentativeSyncStatus::Skipped)
        ->and($representative->sync_payload['reason'])->toBe('aws_backend_not_enabled');
});

it('projects representatives locally and syncs the curated face ids to aws asynchronously', function () {
    $event = Event::factory()->create();
    EventFaceSearchSetting::factory()->create([
        'event_id' => $event->id,
        'enabled' => true,
        'recognition_enabled' => true,
        'search_backend_key' => 'aws_rekognition',
        'aws_collection_id' => 'eventovivo-face-search-event-' . $event->id,
    ]);

    $person = EventPerson::factory()->create(['event_id' => $event->id]);
    $media = EventMedia::factory()->create(['event_id' => $event->id]);
    $face = EventMediaFace::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'bbox_x' => 10,
        'bbox_y' => 12,
        'bbox_w' => 140,
        'bbox_h' => 150,
    ]);

    EventPersonFaceAssignment::factory()->create([
        'event_id' => $event->id,
        'event_person_id' => $person->id,
        'event_media_face_id' => $face->id,
        'status' => EventPersonAssignmentStatus::Confirmed->value,
    ]);

    FaceSearchProviderRecord::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'backend_key' => 'aws_rekognition',
        'provider_key' => 'aws_rekognition',
        'collection_id' => 'eventovivo-face-search-event-' . $event->id,
        'face_id' => 'aws-face-123',
        'bbox_json' => [
            'x' => 10,
            'y' => 12,
            'width' => 140,
            'height' => 150,
        ],
        'searchable' => true,
    ]);

    $backend = \Mockery::mock(AwsRekognitionFaceSearchBackend::class);
    $backend->shouldReceive('ensureEventBackend')->once()->andReturn([
        'status' => 'ready',
    ]);
    $backend->shouldReceive('syncUserVector')
        ->once()
        ->withArgs(function (Event $argEvent, EventFaceSearchSetting $settings, string $userId, array $faceIds) use ($event, $person): bool {
            return (int) $argEvent->id === (int) $event->id
                && $userId === sprintf('evt:%d:person:%d', $event->id, $person->id)
                && $faceIds === ['aws-face-123'];
        })
        ->andReturn([
            'user_id' => sprintf('evt:%d:person:%d', $event->id, $person->id),
            'associated_face_count' => 1,
        ]);

    app()->instance(AwsRekognitionFaceSearchBackend::class, $backend);

    (new SyncEventPersonRepresentativeFacesJob($event->id, $person->id))->handle();

    $representative = EventPersonRepresentativeFace::query()->firstOrFail();

    expect($representative->sync_status)->toBe(EventPersonRepresentativeSyncStatus::Synced)
        ->and($representative->sync_payload['provider_face_id'])->toBe('aws-face-123');
});

