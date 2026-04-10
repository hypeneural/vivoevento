<?php

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Actions\QueueEventFaceSearchReindexAction;
use App\Modules\FaceSearch\Jobs\BackfillEventFaceSearchGalleryJob;
use Illuminate\Support\Facades\Log;
use Mockery as m;

it('waits for the aws collection metadata before running the legacy gallery backfill', function () {
    $event = Event::factory()->create();

    \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'recognition_enabled' => true,
        'search_backend_key' => 'aws_rekognition',
        'aws_collection_id' => null,
    ]);

    $action = m::mock(QueueEventFaceSearchReindexAction::class);
    $action->shouldNotReceive('execute');

    Log::shouldReceive('channel')
        ->once()
        ->andReturnSelf();
    Log::shouldReceive('info')
        ->once()
        ->withArgs(fn (string $message, array $context) => $message === 'face_search.backfill.waiting_for_collection'
            && ($context['event_id'] ?? null) === $event->id);

    $job = new BackfillEventFaceSearchGalleryJob($event->id);

    $job->handle($action);
});

it('queues the gallery backfill without provisioning the collection twice once metadata is ready', function () {
    $event = Event::factory()->create();

    \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'recognition_enabled' => true,
        'search_backend_key' => 'aws_rekognition',
        'aws_collection_id' => 'eventovivo-face-search-event-' . $event->id,
    ]);

    $action = m::mock(QueueEventFaceSearchReindexAction::class);
    $action->shouldReceive('execute')
        ->once()
        ->withArgs(fn (Event $loadedEvent, bool $ensureBackend) => $loadedEvent->id === $event->id && $ensureBackend === false);

    $job = new BackfillEventFaceSearchGalleryJob($event->id);

    $job->handle($action);
});
