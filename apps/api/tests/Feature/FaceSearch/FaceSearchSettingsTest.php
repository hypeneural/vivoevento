<?php

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;

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
        ->assertJsonPath('data.min_face_size_px', 96)
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
        ->assertJsonPath('data.min_quality_score', 0.72)
        ->assertJsonPath('data.search_threshold', 0.28)
        ->assertJsonPath('data.allow_public_selfie_search', true);

    $this->assertDatabaseHas('event_face_search_settings', [
        'event_id' => $event->id,
        'enabled' => true,
        'provider_key' => 'noop',
        'embedding_model_key' => 'face-embedding-foundation-v2',
        'vector_store_key' => 'pgvector',
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
        'min_face_size_px' => 96,
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
        'min_face_size_px' => 96,
        'min_quality_score' => 0.60,
        'search_threshold' => 0.35,
        'top_k' => 50,
        'allow_public_selfie_search' => false,
        'selfie_retention_hours' => 24,
    ]);

    $this->assertApiForbidden($response);

    expect(EventFaceSearchSetting::query()->count())->toBe(0);
});
