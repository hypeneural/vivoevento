<?php

use App\Modules\EventPeople\Actions\ProjectEventPersonRepresentativeFacesAction;
use App\Modules\EventPeople\Enums\EventPersonAssignmentStatus;
use App\Modules\EventPeople\Enums\EventPersonRepresentativeSyncStatus;
use App\Modules\EventPeople\Models\EventPerson;
use App\Modules\EventPeople\Models\EventPersonFaceAssignment;
use App\Modules\EventPeople\Models\EventPersonRepresentativeFace;
use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Models\EventMediaFace;
use App\Modules\MediaProcessing\Models\EventMedia;

it('projects a curated representative set and deduplicates near-identical candidates', function () {
    $event = Event::factory()->create();
    $person = EventPerson::factory()->create(['event_id' => $event->id]);

    $firstMedia = EventMedia::factory()->create(['event_id' => $event->id]);
    $secondMedia = EventMedia::factory()->create(['event_id' => $event->id]);

    $bestFace = EventMediaFace::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $firstMedia->id,
        'quality_score' => 0.95,
        'face_area_ratio' => 0.21,
        'pose_yaw' => 0,
        'pose_pitch' => 0,
    ]);

    $duplicateFace = EventMediaFace::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $firstMedia->id,
        'quality_score' => 0.70,
        'face_area_ratio' => 0.12,
        'pose_yaw' => 2,
        'pose_pitch' => 1,
    ]);

    $secondFace = EventMediaFace::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $secondMedia->id,
        'quality_score' => 0.90,
        'face_area_ratio' => 0.18,
        'pose_yaw' => 22,
        'pose_pitch' => 0,
    ]);

    foreach ([$bestFace, $duplicateFace, $secondFace] as $face) {
        EventPersonFaceAssignment::factory()->create([
            'event_id' => $event->id,
            'event_person_id' => $person->id,
            'event_media_face_id' => $face->id,
            'status' => EventPersonAssignmentStatus::Confirmed->value,
        ]);
    }

    $representatives = app(ProjectEventPersonRepresentativeFacesAction::class)->execute($event, $person->fresh());

    expect($representatives)->toHaveCount(2)
        ->and($representatives->pluck('event_media_face_id')->all())->toBe([$bestFace->id, $secondFace->id]);

    $this->assertDatabaseMissing('event_person_representative_faces', [
        'event_id' => $event->id,
        'event_person_id' => $person->id,
        'event_media_face_id' => $duplicateFace->id,
    ]);

    EventPersonRepresentativeFace::query()
        ->where('event_id', $event->id)
        ->where('event_person_id', $person->id)
        ->update([
            'sync_status' => EventPersonRepresentativeSyncStatus::Synced->value,
            'last_synced_at' => now(),
            'sync_payload' => ['result' => 'kept'],
        ]);

    $rerun = app(ProjectEventPersonRepresentativeFacesAction::class)->execute($event, $person->fresh());

    expect($rerun->pluck('sync_status')->map(fn ($status) => $status->value)->all())
        ->toBe([
            EventPersonRepresentativeSyncStatus::Synced->value,
            EventPersonRepresentativeSyncStatus::Synced->value,
        ]);
});

