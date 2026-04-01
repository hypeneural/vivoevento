<?php

use App\Modules\Events\Models\Event;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Events\MediaDeleted;
use App\Modules\MediaProcessing\Events\MediaPublished;
use App\Modules\MediaProcessing\Events\MediaRejected;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Wall\Events\WallMediaDeleted;
use App\Modules\Wall\Events\WallMediaPublished;
use App\Modules\Wall\Models\EventWallSetting;
use Illuminate\Support\Facades\Event as EventFacade;

it('broadcasts a wall publish event when a media published event is dispatched', function () {
    $domainEvent = Event::factory()->active()->create();

    $settings = EventWallSetting::factory()->live()->create([
        'event_id' => $domainEvent->id,
    ]);

    $media = EventMedia::factory()->published()->create([
        'event_id' => $domainEvent->id,
    ]);

    EventFacade::fake([WallMediaPublished::class]);

    event(MediaPublished::fromMedia($media));

    EventFacade::assertDispatched(WallMediaPublished::class, function (WallMediaPublished $event) use ($settings, $media) {
        return $event->wallCode === $settings->wall_code
            && $event->payload['id'] === 'media_'.$media->id;
    });
});

it('dispatches a media published domain event when approving already published media', function () {
    [$user, $organization] = $this->actingAsOwner();

    $domainEvent = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $domainEvent->id,
        'moderation_status' => ModerationStatus::Pending->value,
        'publication_status' => PublicationStatus::Published->value,
        'published_at' => now(),
    ]);

    EventFacade::fake([MediaPublished::class]);

    $response = $this->apiPost("/media/{$media->id}/approve");

    $this->assertApiSuccess($response);

    EventFacade::assertDispatched(MediaPublished::class, fn (MediaPublished $event) => $event->eventMediaId === $media->id);
});

it('dispatches a media rejected domain event when rejecting media', function () {
    [$user, $organization] = $this->actingAsOwner();

    $domainEvent = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $domainEvent->id,
    ]);

    EventFacade::fake([MediaRejected::class]);

    $response = $this->apiPost("/media/{$media->id}/reject");

    $this->assertApiSuccess($response);

    EventFacade::assertDispatched(MediaRejected::class, fn (MediaRejected $event) => $event->eventMediaId === $media->id);
});

it('dispatches a media deleted domain event when deleting media', function () {
    [$user, $organization] = $this->actingAsOwner();

    $domainEvent = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $domainEvent->id,
    ]);

    EventFacade::fake([MediaDeleted::class]);

    $response = $this->apiDelete("/media/{$media->id}");

    $response->assertStatus(204);

    EventFacade::assertDispatched(MediaDeleted::class, fn (MediaDeleted $event) => $event->eventMediaId === $media->id);
});

it('broadcasts a wall delete event when a media rejected event is dispatched', function () {
    $domainEvent = Event::factory()->active()->create();

    $settings = EventWallSetting::factory()->live()->create([
        'event_id' => $domainEvent->id,
    ]);

    $media = EventMedia::factory()->published()->create([
        'event_id' => $domainEvent->id,
    ]);

    EventFacade::fake([WallMediaDeleted::class]);

    event(MediaRejected::fromMedia($media));

    EventFacade::assertDispatched(WallMediaDeleted::class, function (WallMediaDeleted $event) use ($settings, $media) {
        return $event->wallCode === $settings->wall_code
            && $event->payload['id'] === 'media_'.$media->id;
    });
});
