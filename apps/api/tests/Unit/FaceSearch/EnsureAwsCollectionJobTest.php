<?php

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Jobs\EnsureAwsCollectionJob;
use App\Modules\FaceSearch\Services\AwsRekognitionFaceSearchBackend;
use Mockery as m;

it('calls the aws backend to ensure the collection for an eligible event', function () {
    $event = Event::factory()->create();

    \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'recognition_enabled' => true,
        'search_backend_key' => 'aws_rekognition',
    ]);

    $backend = m::mock(AwsRekognitionFaceSearchBackend::class);
    $backend->shouldReceive('ensureEventBackend')
        ->once()
        ->withArgs(fn ($loadedEvent, $settings) => $loadedEvent->id === $event->id && $settings->event_id === $event->id);

    $job = new EnsureAwsCollectionJob($event->id);

    $job->handle($backend);
});

it('does nothing when the event is not configured to use aws rekognition', function () {
    $event = Event::factory()->create();

    \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'recognition_enabled' => false,
        'search_backend_key' => 'local_pgvector',
    ]);

    $backend = m::mock(AwsRekognitionFaceSearchBackend::class);
    $backend->shouldNotReceive('ensureEventBackend');

    $job = new EnsureAwsCollectionJob($event->id);

    $job->handle($backend);
});

it('uses a stable unique lock per event to avoid duplicate collection provisioning jobs', function () {
    $job = new EnsureAwsCollectionJob(123);

    expect($job->uniqueId())->toBe('face-search-ensure-aws:123')
        ->and($job->uniqueFor)->toBe(900);
});
