<?php

use App\Modules\Events\Models\Event;

beforeEach(function () {
    if (! filter_var(env('RUN_FACE_SEARCH_AWS_TDD', false), FILTER_VALIDATE_BOOL)) {
        $this->markTestSkipped('AWS FaceSearch TDD contracts are opt-in. Set RUN_FACE_SEARCH_AWS_TDD=1 to execute them.');
    }
});

it('accepts aws backend settings separately from the local pgvector thresholds', function () {
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
        'aws_region' => 'us-east-1',
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
    $response->assertJsonPath('data.search_backend_key', 'aws_rekognition')
        ->assertJsonPath('data.fallback_backend_key', 'local_pgvector')
        ->assertJsonPath('data.routing_policy', 'aws_primary_local_fallback')
        ->assertJsonPath('data.aws_region', 'us-east-1')
        ->assertJsonPath('data.aws_search_face_match_threshold', 80)
        ->assertJsonPath('data.aws_associate_user_match_threshold', 75);
});
