<?php

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\FaceSearch\Jobs\EnsureAwsCollectionJob;
use Illuminate\Support\Facades\Bus;

it('returns default face search settings for an event when none were persisted yet', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->apiGet("/events/{$event->id}/face-search/settings");

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.enabled', false)
        ->assertJsonPath('data.provider_key', 'noop')
        ->assertJsonPath('data.vector_store_key', 'pgvector')
        ->assertJsonPath('data.search_strategy', 'exact')
        ->assertJsonPath('data.min_face_size_px', 24)
        ->assertJsonPath('data.search_threshold', 0.5)
        ->assertJsonPath('data.top_k', 50);
});

it('updates face search settings for an event', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->apiPatch("/events/{$event->id}/face-search/settings", [
        'enabled' => true,
        'provider_key' => 'noop',
        'embedding_model_key' => 'face-embedding-foundation-v2',
        'vector_store_key' => 'pgvector',
        'search_strategy' => 'ann',
        'min_face_size_px' => 112,
        'min_quality_score' => 0.72,
        'search_threshold' => 0.28,
        'top_k' => 80,
        'allow_public_selfie_search' => true,
        'selfie_retention_hours' => 12,
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.embedding_model_key', 'face-embedding-foundation-v2')
        ->assertJsonPath('data.search_strategy', 'ann')
        ->assertJsonPath('data.min_quality_score', 0.72)
        ->assertJsonPath('data.search_threshold', 0.28)
        ->assertJsonPath('data.allow_public_selfie_search', true);

    $this->assertDatabaseHas('event_face_search_settings', [
        'event_id' => $event->id,
        'enabled' => true,
        'provider_key' => 'noop',
        'embedding_model_key' => 'face-embedding-foundation-v2',
        'vector_store_key' => 'pgvector',
        'search_strategy' => 'ann',
        'min_face_size_px' => 112,
        'top_k' => 80,
        'allow_public_selfie_search' => true,
        'selfie_retention_hours' => 12,
    ]);
});

it('accepts compreface as the face search provider', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->apiPatch("/events/{$event->id}/face-search/settings", [
        'enabled' => true,
        'provider_key' => 'compreface',
        'embedding_model_key' => 'compreface-face-v1',
        'vector_store_key' => 'pgvector',
        'search_strategy' => 'exact',
        'min_face_size_px' => 112,
        'min_quality_score' => 0.72,
        'search_threshold' => 0.28,
        'top_k' => 80,
        'allow_public_selfie_search' => false,
        'selfie_retention_hours' => 12,
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.provider_key', 'compreface')
        ->assertJsonPath('data.embedding_model_key', 'compreface-face-v1');

    $this->assertDatabaseHas('event_face_search_settings', [
        'event_id' => $event->id,
        'enabled' => true,
        'provider_key' => 'compreface',
        'embedding_model_key' => 'compreface-face-v1',
    ]);
});

it('validates that public selfie search requires face search enabled', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->apiPatch("/events/{$event->id}/face-search/settings", [
        'enabled' => false,
        'provider_key' => 'noop',
        'embedding_model_key' => 'face-embedding-foundation-v1',
        'vector_store_key' => 'pgvector',
        'search_strategy' => 'exact',
        'min_face_size_px' => 24,
        'min_quality_score' => 0.60,
        'search_threshold' => 0.35,
        'top_k' => 50,
        'allow_public_selfie_search' => true,
        'selfie_retention_hours' => 24,
    ]);

    $this->assertApiValidationError($response, ['allow_public_selfie_search']);
});

it('forbids updating face search settings without permission in the event organization', function () {
    [$user, $organization] = $this->actingAsViewer();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->apiPatch("/events/{$event->id}/face-search/settings", [
        'enabled' => true,
        'provider_key' => 'noop',
        'embedding_model_key' => 'face-embedding-foundation-v1',
        'vector_store_key' => 'pgvector',
        'search_strategy' => 'exact',
        'min_face_size_px' => 24,
        'min_quality_score' => 0.60,
        'search_threshold' => 0.35,
        'top_k' => 50,
        'allow_public_selfie_search' => false,
        'selfie_retention_hours' => 24,
    ]);

    $this->assertApiForbidden($response);

    expect(EventFaceSearchSetting::query()->count())->toBe(0);
});

it('dispatches aws collection provisioning when the event activates the aws backend', function () {
    Bus::fake();

    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->apiPatch("/events/{$event->id}/face-search/settings", [
        'enabled' => true,
        'provider_key' => 'compreface',
        'embedding_model_key' => 'compreface-face-v1',
        'vector_store_key' => 'pgvector',
        'search_strategy' => 'exact',
        'min_face_size_px' => 24,
        'min_quality_score' => 0.60,
        'search_threshold' => 0.50,
        'top_k' => 50,
        'allow_public_selfie_search' => false,
        'selfie_retention_hours' => 24,
        'recognition_enabled' => true,
        'search_backend_key' => 'aws_rekognition',
        'fallback_backend_key' => 'local_pgvector',
        'routing_policy' => 'aws_primary_local_fallback',
        'aws_region' => 'eu-central-1',
        'aws_search_mode' => 'faces',
        'aws_index_quality_filter' => 'AUTO',
        'aws_search_faces_quality_filter' => 'NONE',
        'aws_search_users_quality_filter' => 'NONE',
        'aws_search_face_match_threshold' => 80,
        'aws_search_user_match_threshold' => 80,
        'aws_associate_user_match_threshold' => 75,
        'aws_max_faces_per_image' => 100,
        'aws_index_profile_key' => 'social_gallery_event',
        'aws_detection_attributes_json' => ['DEFAULT', 'FACE_OCCLUDED'],
    ]);

    $this->assertApiSuccess($response);

    Bus::assertDispatched(EnsureAwsCollectionJob::class, fn (EnsureAwsCollectionJob $job) => $job->eventId === $event->id);
});
