<?php

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\Events\Models\EventMediaSenderBlacklist;
use App\Modules\InboundMedia\Models\InboundMessage;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Wall\Events\WallMediaDeleted;
use App\Modules\Wall\Events\WallMediaPublished;
use App\Modules\Wall\Models\EventWallSetting;
use Illuminate\Support\Facades\Event as EventFacade;
use Spatie\Activitylog\Models\Activity;

it('lists approved gallery media for the current organization using gallery filters', function () {
    [, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    $otherEventInOrg = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    $otherOrganization = Organization::factory()->create();
    $foreignEvent = Event::factory()->active()->create([
        'organization_id' => $otherOrganization->id,
    ]);

    $expected = EventMedia::factory()->approved()->create([
        'event_id' => $event->id,
        'publication_status' => PublicationStatus::Hidden->value,
        'is_featured' => true,
        'sort_order' => 25,
        'source_type' => 'whatsapp',
    ]);

    EventMedia::factory()->approved()->create([
        'event_id' => $event->id,
        'publication_status' => PublicationStatus::Hidden->value,
        'is_featured' => false,
        'sort_order' => 10,
    ]);

    EventMedia::factory()->approved()->create([
        'event_id' => $otherEventInOrg->id,
        'publication_status' => PublicationStatus::Hidden->value,
        'is_featured' => true,
        'sort_order' => 30,
    ]);

    EventMedia::factory()->create([
        'event_id' => $event->id,
        'moderation_status' => ModerationStatus::Pending->value,
        'publication_status' => PublicationStatus::Hidden->value,
        'is_featured' => true,
    ]);

    EventMedia::factory()->approved()->create([
        'event_id' => $foreignEvent->id,
        'publication_status' => PublicationStatus::Hidden->value,
        'is_featured' => true,
        'sort_order' => 40,
    ]);

    $response = $this->apiGet(
        "/gallery?event_id={$event->id}&publication_status=hidden&featured=1&sort_by=sort_order&sort_direction=desc"
    );

    $this->assertApiPaginated($response);

    $response->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $expected->id)
        ->assertJsonPath('data.0.event_id', $event->id)
        ->assertJsonPath('data.0.publication_status', 'hidden')
        ->assertJsonPath('data.0.is_featured', true)
        ->assertJsonPath('data.0.channel', 'whatsapp')
        ->assertJsonPath('meta.stats.total', 1)
        ->assertJsonPath('meta.stats.featured', 1);
});

it('publishes approved gallery media and propagates the wall event', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    EventModule::query()->create([
        'event_id' => $event->id,
        'module_key' => 'wall',
        'is_enabled' => true,
    ]);

    $settings = EventWallSetting::factory()->live()->create([
        'event_id' => $event->id,
    ]);

    $media = EventMedia::factory()->approved()->create([
        'event_id' => $event->id,
        'publication_status' => PublicationStatus::Draft->value,
        'published_at' => null,
    ]);

    EventFacade::fake([WallMediaPublished::class]);

    $response = $this->apiPost("/events/{$event->id}/gallery/{$media->id}/publish");

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.publication_status', 'published')
        ->assertJsonPath('data.published_at', fn ($value) => filled($value));

    $media->refresh();

    expect($media->publication_status)->toBe(PublicationStatus::Published)
        ->and($media->published_at)->not->toBeNull();

    EventFacade::assertDispatched(
        WallMediaPublished::class,
        fn (WallMediaPublished $eventPayload) => $eventPayload->wallCode === $settings->wall_code
            && $eventPayload->payload['id'] === 'media_'.$media->id,
    );

    $activity = Activity::query()
        ->where('event', 'gallery.published')
        ->where('subject_type', EventMedia::class)
        ->where('subject_id', $media->id)
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity?->causer_id)->toBe($user->id);
});

it('rejects gallery publication when the media is not approved', function () {
    [, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'moderation_status' => ModerationStatus::Pending->value,
        'publication_status' => PublicationStatus::Draft->value,
    ]);

    $response = $this->apiPost("/events/{$event->id}/gallery/{$media->id}/publish");

    $response->assertStatus(422)
        ->assertJsonValidationErrors('media');
});

it('hides published gallery media and propagates the wall removal event', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    EventModule::query()->create([
        'event_id' => $event->id,
        'module_key' => 'wall',
        'is_enabled' => true,
    ]);

    $settings = EventWallSetting::factory()->live()->create([
        'event_id' => $event->id,
    ]);

    $media = EventMedia::factory()->published()->create([
        'event_id' => $event->id,
    ]);

    EventFacade::fake([WallMediaDeleted::class]);

    $response = $this->apiDelete("/events/{$event->id}/gallery/{$media->id}");

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.publication_status', 'hidden')
        ->assertJsonPath('data.published_at', null);

    $media->refresh();

    expect($media->publication_status)->toBe(PublicationStatus::Hidden)
        ->and($media->published_at)->toBeNull();

    EventFacade::assertDispatched(
        WallMediaDeleted::class,
        fn (WallMediaDeleted $eventPayload) => $eventPayload->wallCode === $settings->wall_code
            && $eventPayload->payload['id'] === 'media_'.$media->id,
    );

    $activity = Activity::query()
        ->where('event', 'gallery.hidden')
        ->where('subject_type', EventMedia::class)
        ->where('subject_id', $media->id)
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity?->causer_id)->toBe($user->id);
});

it('returns sender context in gallery catalog and allows sender identity search', function () {
    [, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'current_entitlements_json' => [
            'channels' => [
                'blacklist' => [
                    'enabled' => true,
                ],
            ],
        ],
    ]);

    $inbound = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'zapi',
        'message_id' => 'gallery-zapi-001',
        'message_type' => 'image',
        'chat_external_id' => '120363499999999999-group',
        'sender_external_id' => '11111111111111@lid',
        'sender_phone' => '554899991111',
        'sender_lid' => '11111111111111@lid',
        'sender_name' => 'Ana Martins',
        'sender_avatar_url' => 'https://cdn.eventovivo.test/ana.jpg',
        'status' => 'processed',
        'received_at' => now()->subMinutes(5),
    ]);

    $media = EventMedia::factory()->approved()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $inbound->id,
        'publication_status' => PublicationStatus::Hidden->value,
        'source_type' => 'whatsapp',
    ]);

    EventMediaSenderBlacklist::factory()->create([
        'event_id' => $event->id,
        'identity_type' => 'lid',
        'identity_value' => '11111111111111@lid',
        'is_active' => true,
    ]);

    $response = $this->apiGet("/gallery?event_id={$event->id}&search=11111111111111@lid");

    $this->assertApiPaginated($response);
    $response->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $media->id)
        ->assertJsonPath('data.0.sender_name', 'Ana Martins')
        ->assertJsonPath('data.0.sender_lid', '11111111111111@lid')
        ->assertJsonPath('data.0.sender_avatar_url', 'https://cdn.eventovivo.test/ana.jpg')
        ->assertJsonPath('data.0.sender_blocked', true)
        ->assertJsonPath('data.0.sender_recommended_identity_type', 'lid');
});
