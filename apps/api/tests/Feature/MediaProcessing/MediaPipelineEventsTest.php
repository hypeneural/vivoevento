<?php

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Events\MediaDeleted;
use App\Modules\MediaProcessing\Events\MediaHidden;
use App\Modules\MediaProcessing\Events\MediaPublished;
use App\Modules\MediaProcessing\Events\MediaRejected;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Wall\Events\WallMediaDeleted;
use App\Modules\Wall\Events\WallMediaPublished;
use App\Modules\Wall\Models\EventWallSetting;
use Illuminate\Support\Facades\Event as EventFacade;
use Spatie\Activitylog\Models\Activity;

it('broadcasts a wall publish event when a media published event is dispatched', function () {
    $domainEvent = Event::factory()->active()->create();

    EventModule::query()->create([
        'event_id' => $domainEvent->id,
        'module_key' => 'wall',
        'is_enabled' => true,
    ]);

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

it('does not broadcast a wall publish event when media orientation mismatches the wall policy', function () {
    $domainEvent = Event::factory()->active()->create();

    EventModule::query()->create([
        'event_id' => $domainEvent->id,
        'module_key' => 'wall',
        'is_enabled' => true,
    ]);

    EventWallSetting::factory()->live()->create([
        'event_id' => $domainEvent->id,
        'accepted_orientation' => 'landscape',
    ]);

    $media = EventMedia::factory()->published()->create([
        'event_id' => $domainEvent->id,
        'media_type' => 'video',
        'mime_type' => 'video/mp4',
        'width' => 1080,
        'height' => 1920,
    ]);

    EventFacade::fake([WallMediaPublished::class]);

    event(MediaPublished::fromMedia($media));

    EventFacade::assertNotDispatched(WallMediaPublished::class);
});

it('does not broadcast a wall publish event for original-only videos when strict wall video gate is enabled', function () {
    $domainEvent = Event::factory()->active()->create();

    EventModule::query()->create([
        'event_id' => $domainEvent->id,
        'module_key' => 'wall',
        'is_enabled' => true,
    ]);

    EventWallSetting::factory()->live()->create([
        'event_id' => $domainEvent->id,
    ]);

    $media = EventMedia::factory()->published()->create([
        'event_id' => $domainEvent->id,
        'media_type' => 'video',
        'mime_type' => 'video/mp4',
        'width' => 1920,
        'height' => 1080,
        'duration_seconds' => 18,
        'video_codec' => 'h264',
        'container' => 'mp4',
    ]);

    EventFacade::fake([WallMediaPublished::class]);

    event(MediaPublished::fromMedia($media));

    EventFacade::assertNotDispatched(WallMediaPublished::class);
});

it('broadcasts the chosen wall variant and poster for eligible wall videos', function () {
    $domainEvent = Event::factory()->active()->create();

    EventModule::query()->create([
        'event_id' => $domainEvent->id,
        'module_key' => 'wall',
        'is_enabled' => true,
    ]);

    $settings = EventWallSetting::factory()->live()->create([
        'event_id' => $domainEvent->id,
    ]);

    $media = EventMedia::factory()->published()->create([
        'event_id' => $domainEvent->id,
        'media_type' => 'video',
        'mime_type' => 'video/mp4',
        'width' => 1280,
        'height' => 720,
        'duration_seconds' => 18,
        'video_codec' => 'h264',
        'audio_codec' => 'aac',
        'container' => 'mp4',
    ]);

    $media->variants()->create([
        'variant_key' => 'wall_video_720p',
        'disk' => 'public',
        'path' => "events/{$domainEvent->id}/variants/{$media->id}/wall_video_720p.mp4",
        'mime_type' => 'video/mp4',
        'width' => 1280,
        'height' => 720,
    ]);

    $media->variants()->create([
        'variant_key' => 'wall_video_poster',
        'disk' => 'public',
        'path' => "events/{$domainEvent->id}/variants/{$media->id}/wall_video_poster.jpg",
        'mime_type' => 'image/jpeg',
        'width' => 1280,
        'height' => 720,
    ]);

    EventFacade::fake([WallMediaPublished::class]);

    event(MediaPublished::fromMedia($media->fresh(['variants'])));

    EventFacade::assertDispatched(WallMediaPublished::class, function (WallMediaPublished $event) use ($settings, $media, $domainEvent) {
        return $event->wallCode === $settings->wall_code
            && $event->payload['id'] === 'media_'.$media->id
            && $event->payload['served_variant_key'] === 'wall_video_720p'
            && $event->payload['preview_variant_key'] === 'wall_video_poster'
            && $event->payload['url'] === rtrim((string) config('app.url'), '/')."/storage/events/{$domainEvent->id}/variants/{$media->id}/wall_video_720p.mp4";
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
    $response->assertJsonPath('data.decision_source', 'user_override')
        ->assertJsonPath('data.decision_overridden_by_user_id', $user->id);

    EventFacade::assertDispatched(MediaPublished::class, fn (MediaPublished $event) => $event->eventMediaId === $media->id);

    $activity = Activity::query()
        ->where('description', 'Midia aprovada')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity?->event)->toBe('media.approved');
    expect($activity?->subject_type)->toBe(EventMedia::class);
    expect($activity?->subject_id)->toBe($media->id);
    expect($media->fresh()->decision_source?->value)->toBe('user_override');
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
    $response->assertJsonPath('data.decision_source', 'user_override')
        ->assertJsonPath('data.decision_overridden_by_user_id', $user->id)
        ->assertJsonPath('data.publication_status', 'draft');

    EventFacade::assertDispatched(MediaRejected::class, fn (MediaRejected $event) => $event->eventMediaId === $media->id);

    $activity = Activity::query()
        ->where('description', 'Midia rejeitada')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity?->event)->toBe('media.rejected');
    expect($activity?->subject_type)->toBe(EventMedia::class);
    expect($activity?->subject_id)->toBe($media->id);
    expect($media->fresh()->decision_source?->value)->toBe('user_override');
    expect($media->fresh()->published_at)->toBeNull();
});

it('reapproves rejected media while keeping manual override tracking', function () {
    [$user, $organization] = $this->actingAsOwner();

    $domainEvent = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $domainEvent->id,
        'moderation_status' => ModerationStatus::Rejected->value,
        'publication_status' => PublicationStatus::Draft->value,
    ]);

    $response = $this->apiPost("/media/{$media->id}/approve", [
        'reason' => 'Revisado e liberado manualmente',
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.decision_source', 'user_override')
        ->assertJsonPath('data.decision_override_reason', 'Revisado e liberado manualmente')
        ->assertJsonPath('data.decision_overridden_by_user_id', $user->id)
        ->assertJsonPath('data.moderation_status', 'approved');

    $media->refresh();

    expect($media->decision_source?->value)->toBe('user_override')
        ->and($media->decision_override_reason)->toBe('Revisado e liberado manualmente')
        ->and($media->decision_overridden_by_user_id)->toBe($user->id)
        ->and($media->moderation_status)->toBe(ModerationStatus::Approved);
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

    $activity = Activity::query()
        ->where('description', 'Midia removida')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity?->event)->toBe('media.deleted');
    expect($activity?->subject_type)->toBe(EventMedia::class);
    expect($activity?->subject_id)->toBe($media->id);
});

it('broadcasts a wall delete event when a media rejected event is dispatched', function () {
    $domainEvent = Event::factory()->active()->create();

    EventModule::query()->create([
        'event_id' => $domainEvent->id,
        'module_key' => 'wall',
        'is_enabled' => true,
    ]);

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

it('broadcasts a wall delete event when a media hidden event is dispatched', function () {
    $domainEvent = Event::factory()->active()->create();

    EventModule::query()->create([
        'event_id' => $domainEvent->id,
        'module_key' => 'wall',
        'is_enabled' => true,
    ]);

    $settings = EventWallSetting::factory()->live()->create([
        'event_id' => $domainEvent->id,
    ]);

    $media = EventMedia::factory()->published()->create([
        'event_id' => $domainEvent->id,
    ]);

    EventFacade::fake([WallMediaDeleted::class]);

    event(MediaHidden::fromMedia($media));

    EventFacade::assertDispatched(WallMediaDeleted::class, function (WallMediaDeleted $event) use ($settings, $media) {
        return $event->wallCode === $settings->wall_code
            && $event->payload['id'] === 'media_'.$media->id;
    });
});
