<?php

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Jobs\IndexMediaFacesJob;
use App\Modules\FaceSearch\Jobs\ReconcileAwsCollectionJob;
use App\Modules\FaceSearch\Services\AwsRekognitionFaceSearchBackend;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Support\Facades\Bus;
use Mockery as m;

it('returns a health snapshot for the aws face search backend', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'recognition_enabled' => true,
        'search_backend_key' => 'aws_rekognition',
        'aws_collection_id' => 'eventovivo-face-search-event-' . $event->id,
    ]);

    $backend = m::mock(AwsRekognitionFaceSearchBackend::class);
    $backend->shouldReceive('key')->andReturn('aws_rekognition');
    $backend->shouldReceive('healthCheck')
        ->once()
        ->andReturn([
            'backend_key' => 'aws_rekognition',
            'status' => 'healthy',
            'checks' => [
                'identity' => 'ok',
                'collection' => 'ok',
                'list_faces' => 'ok',
            ],
        ]);

    app()->instance(AwsRekognitionFaceSearchBackend::class, $backend);

    $response = $this->apiGet("/events/{$event->id}/face-search/health");

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.backend_key', 'aws_rekognition')
        ->assertJsonPath('data.status', 'healthy')
        ->assertJsonPath('data.checks.identity', 'ok')
        ->assertJsonPath('data.checks.collection', 'ok');

    expect(data_get($response->json(), 'data.checked_at'))->toBeString();
});

it('queues a face-search reindex run for event images', function () {
    Bus::fake();

    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'recognition_enabled' => true,
        'search_backend_key' => 'aws_rekognition',
        'aws_collection_id' => 'eventovivo-face-search-event-' . $event->id,
    ]);

    EventMedia::factory()->count(2)->create([
        'event_id' => $event->id,
        'media_type' => 'image',
    ]);
    EventMedia::factory()->create([
        'event_id' => $event->id,
        'media_type' => 'video',
    ]);
    EventMedia::factory()->create([
        'media_type' => 'image',
    ]);

    $backend = m::mock(AwsRekognitionFaceSearchBackend::class);
    $backend->shouldReceive('key')->andReturn('aws_rekognition');
    $backend->shouldReceive('ensureEventBackend')
        ->once()
        ->andReturn([
            'backend_key' => 'aws_rekognition',
            'status' => 'ready',
        ]);

    app()->instance(AwsRekognitionFaceSearchBackend::class, $backend);

    $response = $this->apiPost("/events/{$event->id}/face-search/reindex");

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.status', 'queued')
        ->assertJsonPath('data.backend_key', 'aws_rekognition')
        ->assertJsonPath('data.queued_media_count', 2);

    Bus::assertDispatched(IndexMediaFacesJob::class, 2);
});

it('queues aws collection reconciliation from the operational endpoint', function () {
    Bus::fake();

    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'recognition_enabled' => true,
        'search_backend_key' => 'aws_rekognition',
        'aws_collection_id' => 'eventovivo-face-search-event-' . $event->id,
    ]);

    $response = $this->apiPost("/events/{$event->id}/face-search/reconcile");

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.status', 'queued')
        ->assertJsonPath('data.backend_key', 'aws_rekognition')
        ->assertJsonPath('data.job', 'reconcile_collection');

    Bus::assertDispatched(ReconcileAwsCollectionJob::class, fn (ReconcileAwsCollectionJob $job) => $job->eventId === $event->id);
});

it('deletes the aws collection from the operational endpoint', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'recognition_enabled' => true,
        'search_backend_key' => 'aws_rekognition',
        'aws_collection_id' => 'eventovivo-face-search-event-' . $event->id,
    ]);

    $backend = m::mock(AwsRekognitionFaceSearchBackend::class);
    $backend->shouldReceive('deleteEventBackend')
        ->once();

    app()->instance(AwsRekognitionFaceSearchBackend::class, $backend);

    $response = $this->apiDelete("/events/{$event->id}/face-search/collection");

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.status', 'deleted')
        ->assertJsonPath('data.backend_key', 'aws_rekognition');
});

it('forbids operational face-search endpoints without permission in the event organization', function () {
    [$user, $organization] = $this->actingAsViewer();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->apiGet("/events/{$event->id}/face-search/health");

    $this->assertApiForbidden($response);
});
