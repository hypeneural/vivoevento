<?php

use App\Modules\Events\Models\Event;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Services\MediaEffectiveStateResolver;

it('keeps ai moderation context aligned in the moderation feed when the effective resolver classifies the media as pending_moderation', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'moderation_mode' => 'ai',
    ]);

    \Database\Factories\EventContentModerationSettingFactory::new()->create([
        'event_id' => $event->id,
        'enabled' => true,
        'mode' => 'enforced',
    ]);

    $aiPendingMedia = EventMedia::factory()->approved()->create([
        'event_id' => $event->id,
        'safety_status' => 'review',
        'vlm_status' => 'skipped',
        'created_at' => now()->subMinute(),
    ]);

    $rawPendingMedia = EventMedia::factory()->create([
        'event_id' => $event->id,
        'created_at' => now(),
    ]);

    $resolvedState = app(MediaEffectiveStateResolver::class)->resolve(
        $aiPendingMedia->fresh(['event.contentModerationSettings', 'event.mediaIntelligenceSettings']),
    );

    expect($resolvedState['effective_media_state'])->toBe('pending_moderation');

    $unfilteredFeed = $this->apiGet('/media/feed?per_page=10');
    $unfilteredFeed->assertOk();

    $unfilteredItems = collect($unfilteredFeed->json('data'));
    $aiPendingPayload = $unfilteredItems->firstWhere('id', $aiPendingMedia->id);

    expect($aiPendingPayload)->not->toBeNull()
        ->and(data_get($aiPendingPayload, 'status'))->toBe('pending_moderation');

    $pendingOnlyFeed = $this->apiGet('/media/feed?per_page=10&status=pending_moderation');
    $pendingOnlyFeed->assertOk();

    $pendingOnlyIds = collect($pendingOnlyFeed->json('data'))->pluck('id')->all();

    expect($pendingOnlyIds)->toContain($rawPendingMedia->id)
        ->and($pendingOnlyIds)->toContain($aiPendingMedia->id);
});

it('does not expose original-backed moderation surfaces in the feed when dedicated moderation variants are missing', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'media_type' => 'image',
        'original_disk' => 'public',
        'original_path' => "events/{$event->id}/originals/entrada.jpg",
        'original_filename' => 'entrada.jpg',
    ]);

    $feedResponse = $this->apiGet('/media/feed?per_page=10');

    $feedResponse->assertOk();

    $payload = collect($feedResponse->json('data'))->firstWhere('id', $media->id);

    expect($payload)->not->toBeNull()
        ->and(data_get($payload, 'thumbnail_source'))->toBe('original')
        ->and(data_get($payload, 'moderation_thumbnail_url'))->toBeNull()
        ->and(data_get($payload, 'moderation_thumbnail_source'))->toBeNull()
        ->and(data_get($payload, 'moderation_preview_url'))->toBeNull()
        ->and(data_get($payload, 'moderation_preview_source'))->toBeNull();
});
