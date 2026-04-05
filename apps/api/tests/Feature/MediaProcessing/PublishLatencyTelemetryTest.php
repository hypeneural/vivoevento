<?php

use App\Modules\Events\Models\Event;
use App\Modules\InboundMedia\Models\InboundMessage;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Events\MediaPublished;
use App\Modules\MediaProcessing\Jobs\PublishMediaJob;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Services\MediaPipelineTelemetryService;
use Illuminate\Support\Facades\Event as EventFacade;

it('records publish telemetry when approved inbound media is published', function () {
    $event = Event::factory()->active()->create([
        'moderation_mode' => 'none',
    ]);

    $inboundMessage = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'whatsapp',
        'message_id' => 'msg-telemetry-1',
        'message_type' => 'image',
        'status' => 'received',
        'received_at' => now()->subMinutes(2),
    ]);

    $media = EventMedia::factory()->approved()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $inboundMessage->id,
        'publication_status' => PublicationStatus::Draft->value,
        'published_at' => null,
    ]);

    $telemetry = \Mockery::mock(MediaPipelineTelemetryService::class);
    $telemetry
        ->shouldReceive('recordPublished')
        ->once()
        ->with(\Mockery::on(function (EventMedia $publishedMedia) use ($media, $inboundMessage): bool {
            return $publishedMedia->id === $media->id
                && $publishedMedia->relationLoaded('inboundMessage')
                && $publishedMedia->inboundMessage?->id === $inboundMessage->id
                && $publishedMedia->published_at !== null;
        }));

    app()->instance(MediaPipelineTelemetryService::class, $telemetry);

    EventFacade::fake([MediaPublished::class]);

    app(PublishMediaJob::class, ['eventMediaId' => $media->id])->handle();

    $media->refresh();

    expect($media->published_at)->not->toBeNull()
        ->and($media->publication_status)->toBe(PublicationStatus::Published);

    EventFacade::assertDispatched(MediaPublished::class, fn (MediaPublished $event) => $event->eventMediaId === $media->id);
});
