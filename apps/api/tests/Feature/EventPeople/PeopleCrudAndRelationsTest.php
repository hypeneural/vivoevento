<?php

use App\Modules\EventPeople\Models\EventPerson;
use App\Modules\EventPeople\Models\EventPersonMediaStat;
use App\Modules\EventPeople\Models\EventPersonReferencePhoto;
use App\Modules\EventPeople\Models\EventPersonRelation;
use App\Modules\EventPeople\Models\EventPersonRepresentativeFace;
use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\DTOs\FaceBoundingBoxData;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\FaceSearch\Services\FaceDetectionProviderInterface;
use App\Modules\FaceSearch\Models\EventMediaFace;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

it('exposes operational cockpit counters and separates avatar, photo principal, reference photos and representatives', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'event_type' => 'wedding',
    ]);

    $person = EventPerson::factory()->create([
        'event_id' => $event->id,
        'display_name' => 'Noiva',
        'status' => 'active',
    ]);

    EventPerson::factory()->create([
        'event_id' => $event->id,
        'display_name' => 'Rascunho',
        'status' => 'draft',
    ]);

    $media = EventMedia::factory()->create(['event_id' => $event->id]);
    $face = EventMediaFace::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
    ]);

    $person->forceFill([
        'avatar_media_id' => $media->id,
        'avatar_face_id' => $face->id,
    ])->save();

    EventPersonMediaStat::query()->create([
        'event_id' => $event->id,
        'event_person_id' => $person->id,
        'media_count' => 6,
        'solo_media_count' => 2,
        'with_others_media_count' => 4,
        'published_media_count' => 5,
        'pending_media_count' => 1,
        'best_media_id' => $media->id,
        'latest_media_id' => $media->id,
        'projected_at' => now(),
    ]);

    EventPersonReferencePhoto::query()->create([
        'event_id' => $event->id,
        'event_person_id' => $person->id,
        'source' => 'event_face',
        'event_media_id' => $media->id,
        'event_media_face_id' => $face->id,
        'purpose' => 'both',
        'status' => 'active',
        'quality_score' => 0.92,
        'is_primary_avatar' => true,
    ]);

    EventPersonRepresentativeFace::query()->create([
        'event_id' => $event->id,
        'event_person_id' => $person->id,
        'event_media_face_id' => $face->id,
        'rank_score' => 99.1,
        'quality_score' => 0.92,
        'pose_bucket' => 'center-level',
        'sync_status' => 'pending',
    ]);

    \App\Modules\EventPeople\Models\EventPersonReviewQueueItem::query()->create([
        'event_id' => $event->id,
        'queue_key' => 'unknown-face:' . $face->id,
        'type' => 'unknown_person',
        'status' => 'pending',
        'priority' => 100,
        'event_media_face_id' => $face->id,
        'payload' => ['question' => 'Quem e esta pessoa?'],
        'last_signal_at' => now(),
    ]);

    \App\Modules\EventPeople\Models\EventPersonReviewQueueItem::query()->create([
        'event_id' => $event->id,
        'queue_key' => 'identity-conflict:' . $face->id,
        'type' => 'identity_conflict',
        'status' => 'conflict',
        'priority' => 150,
        'event_person_id' => $person->id,
        'event_media_face_id' => $face->id,
        'payload' => ['question' => 'Essa identidade precisa ser revisada?'],
        'last_signal_at' => now(),
    ]);

    $statusResponse = $this->apiGet("/events/{$event->id}/people/operational-status");

    $this->assertApiSuccess($statusResponse);
    $statusResponse->assertJsonPath('data.people_active', 1)
        ->assertJsonPath('data.people_draft', 1)
        ->assertJsonPath('data.review_queue_pending', 1)
        ->assertJsonPath('data.review_queue_conflict', 1)
        ->assertJsonPath('data.aws_sync_pending', 1);

    $showResponse = $this->apiGet("/events/{$event->id}/people/{$person->id}");

    $this->assertApiSuccess($showResponse);
    $showResponse->assertJsonPath('data.avatar.media_id', $media->id)
        ->assertJsonPath('data.avatar.face_id', $face->id)
        ->assertJsonPath('data.primary_photo.best_media_id', $media->id)
        ->assertJsonPath('data.reference_photos.0.event_media_face_id', $face->id)
        ->assertJsonPath('data.reference_photos.0.purpose', 'both')
        ->assertJsonPath('data.representative_faces.0.event_media_face_id', $face->id)
        ->assertJsonPath('data.representative_faces.0.sync_status', 'pending');
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

it('lists confirmed gallery candidates, saves one as human reference and lets the operator define the primary photo', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $person = EventPerson::factory()->create([
        'event_id' => $event->id,
        'display_name' => 'Debutante',
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
    ]);

    $face = EventMediaFace::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'face_index' => 0,
        'quality_score' => 0.96,
        'quality_tier' => 'search_priority',
        'searchable' => true,
    ]);

    \App\Modules\EventPeople\Models\EventPersonFaceAssignment::query()->create([
        'event_id' => $event->id,
        'event_person_id' => $person->id,
        'event_media_face_id' => $face->id,
        'source' => 'manual_confirmed',
        'status' => 'confirmed',
        'reviewed_by' => $user->id,
        'reviewed_at' => now(),
    ]);

    $candidatesResponse = $this->apiGet("/events/{$event->id}/people/{$person->id}/reference-photo-candidates");

    $this->assertApiSuccess($candidatesResponse);
    $candidatesResponse->assertJsonPath('data.0.event_media_face_id', $face->id)
        ->assertJsonPath('data.0.event_media_id', $media->id)
        ->assertJsonPath('data.0.face_index', 0);

    $storeResponse = $this->apiPost("/events/{$event->id}/people/{$person->id}/reference-photos/gallery-face", [
        'event_media_face_id' => $face->id,
        'purpose' => 'matching',
    ]);

    $this->assertApiSuccess($storeResponse);
    $storeResponse->assertJsonPath('data.reference_photos.0.event_media_face_id', $face->id)
        ->assertJsonPath('data.reference_photos.0.source', 'event_face')
        ->assertJsonPath('data.primary_photo', null);

    $referencePhotoId = (int) $storeResponse->json('data.reference_photos.0.id');

    $setPrimaryResponse = $this->apiPost("/events/{$event->id}/people/{$person->id}/reference-photos/{$referencePhotoId}/primary");

    $this->assertApiSuccess($setPrimaryResponse);
    $setPrimaryResponse->assertJsonPath('data.primary_photo.reference_photo_id', $referencePhotoId)
        ->assertJsonPath('data.primary_photo.selection_mode', 'manual')
        ->assertJsonPath('data.primary_photo.event_media_face_id', $face->id);

    $this->assertDatabaseHas('event_people', [
        'id' => $person->id,
        'primary_reference_photo_id' => $referencePhotoId,
    ]);
});

it('uploads a manual human reference photo with dominant-face validation and stores it separately from technical representatives', function () {
    [$user, $organization] = $this->actingAsOwner();

    Storage::fake('public');

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $person = EventPerson::factory()->create([
        'event_id' => $event->id,
        'display_name' => 'Proprietario',
    ]);

    EventFaceSearchSetting::factory()->enabled()->create([
        'event_id' => $event->id,
    ]);

    app()->instance(FaceDetectionProviderInterface::class, new class implements FaceDetectionProviderInterface
    {
        public function detect(\App\Modules\MediaProcessing\Models\EventMedia $media, \App\Modules\FaceSearch\Models\EventFaceSearchSetting $settings, string $binary): array
        {
            return [
                new DetectedFaceData(
                    boundingBox: new FaceBoundingBoxData(390, 390, 420, 420),
                    detectionConfidence: 0.99,
                    qualityScore: 0.95,
                    sharpnessScore: 0.91,
                    faceAreaRatio: 0.22,
                    isPrimaryCandidate: true,
                    providerPayload: [
                        'image_width' => 1200,
                        'image_height' => 1200,
                    ],
                ),
            ];
        }
    });

    $response = $this->withHeaders(['Accept' => 'application/json'])->post(
        "/api/v1/events/{$event->id}/people/{$person->id}/reference-photos/upload",
        [
            'file' => UploadedFile::fake()->image('referencia.jpg', 1200, 1200),
        ],
    );

    $this->assertApiSuccess($response, 201);
    $response->assertJsonPath('data.reference_photos.0.source', 'manual_upload')
        ->assertJsonPath('data.reference_photos.0.reference_upload_media_id', 1)
        ->assertJsonPath('data.reference_photos.0.event_media_face_id', null)
        ->assertJsonPath('data.representative_faces', []);

    $this->assertDatabaseHas('event_person_reference_photos', [
        'event_id' => $event->id,
        'event_person_id' => $person->id,
        'source' => 'manual_upload',
        'reference_upload_media_id' => 1,
        'status' => 'active',
    ]);

    $this->assertDatabaseHas('event_media', [
        'id' => 1,
        'event_id' => $event->id,
        'source_type' => 'upload',
        'uploaded_by_user_id' => $user->id,
        'publication_status' => 'draft',
    ]);

    expect(Storage::disk('public')->allFiles("events/{$event->id}/people/reference-uploads"))->toHaveCount(1);
});

it('rejects a manual reference upload when the image is a group photo instead of one dominant person', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $person = EventPerson::factory()->create([
        'event_id' => $event->id,
    ]);

    app()->instance(FaceDetectionProviderInterface::class, new class implements FaceDetectionProviderInterface
    {
        public function detect(\App\Modules\MediaProcessing\Models\EventMedia $media, \App\Modules\FaceSearch\Models\EventFaceSearchSetting $settings, string $binary): array
        {
            return [
                new DetectedFaceData(
                    boundingBox: new FaceBoundingBoxData(10, 10, 180, 180),
                    qualityScore: 0.93,
                ),
                new DetectedFaceData(
                    boundingBox: new FaceBoundingBoxData(220, 10, 180, 180),
                    qualityScore: 0.88,
                ),
            ];
        }
    });

    $response = $this->withHeaders(['Accept' => 'application/json'])->post(
        "/api/v1/events/{$event->id}/people/{$person->id}/reference-photos/upload",
        [
            'file' => UploadedFile::fake()->image('grupo.jpg', 1200, 800),
        ],
    );

    $this->assertApiValidationError($response, ['file']);
    $response->assertJsonPath('errors.file.0', 'Envie uma selfie com apenas uma pessoa visivel. Busca por foto de grupo ainda nao faz parte desta versao.');
});
