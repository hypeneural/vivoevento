<?php

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Models\EventMedia;
use Spatie\Activitylog\Models\Activity;

it('lists media catalog with organization scoped stats and smart filters', function () {
    [$user, $organization] = $this->actingAsOwner();

    $morningWindowStart = now()->subDay()->setTime(9, 0, 0);
    $morningWindowEnd = now()->subDay()->setTime(12, 0, 0);

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'title' => 'Baile Vivo',
    ]);

    $faceSearchEvent = Event::factory()->active()->aiModeration()->create([
        'organization_id' => $organization->id,
        'title' => 'Festival Identidade',
    ]);

    EventFaceSearchSetting::factory()->enabled()->create([
        'event_id' => $faceSearchEvent->id,
    ]);

    $portraitIndexedDuplicate = EventMedia::factory()->create([
        'event_id' => $faceSearchEvent->id,
        'caption' => 'Selfie da noiva',
        'source_type' => 'public_upload',
        'media_type' => 'image',
        'moderation_status' => ModerationStatus::Pending->value,
        'duplicate_group_key' => 'dup-001',
        'face_index_status' => 'indexed',
        'safety_status' => 'pass',
        'vlm_status' => 'completed',
        'decision_source' => 'ai_safety',
        'width' => 1080,
        'height' => 1920,
        'created_at' => $morningWindowStart->copy()->addHour(),
    ]);

    EventMedia::factory()->create([
        'event_id' => $faceSearchEvent->id,
        'caption' => 'Selfie da banda',
        'source_type' => 'public_upload',
        'media_type' => 'image',
        'moderation_status' => ModerationStatus::Pending->value,
        'face_index_status' => 'queued',
        'safety_status' => 'pass',
        'vlm_status' => 'completed',
        'decision_source' => 'ai_safety',
        'width' => 1080,
        'height' => 1920,
        'created_at' => $morningWindowEnd->copy()->addHours(4),
    ]);

    EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'source_type' => 'whatsapp',
        'media_type' => 'video',
        'face_index_status' => 'skipped',
        'safety_status' => 'skipped',
        'vlm_status' => 'skipped',
        'width' => 1920,
        'height' => 1080,
    ]);

    EventMedia::factory()->create([
        'event_id' => Event::factory()->active()->create()->id,
        'caption' => 'Nao deveria aparecer',
    ]);

    $response = $this->apiGet('/media');

    $this->assertApiPaginated($response);
    $response->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.stats.total', 3)
        ->assertJsonPath('meta.stats.images', 2)
        ->assertJsonPath('meta.stats.videos', 1)
        ->assertJsonPath('meta.stats.pending', 2)
        ->assertJsonPath('meta.stats.published', 1)
        ->assertJsonPath('meta.stats.duplicates', 1)
        ->assertJsonPath('meta.stats.face_indexed', 1);

    $filteredResponse = $this->apiGet("/media?event_id={$faceSearchEvent->id}&status=pending_moderation&channel=upload&media_type=image&duplicates=1&face_search_enabled=1&face_index_status=indexed&orientation=portrait&search=SELFIE");

    $this->assertApiPaginated($filteredResponse);
    $filteredResponse->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $portraitIndexedDuplicate->id)
        ->assertJsonPath('data.0.channel', 'upload')
        ->assertJsonPath('data.0.is_duplicate_candidate', true)
        ->assertJsonPath('data.0.face_index_status', 'indexed')
        ->assertJsonPath('data.0.event_face_search_enabled', true)
        ->assertJsonPath('data.0.orientation', 'portrait');

    $periodResponse = $this->apiGet(sprintf(
        '/media?event_id=%d&created_from=%s&created_to=%s',
        $faceSearchEvent->id,
        urlencode($morningWindowStart->toIso8601String()),
        urlencode($morningWindowEnd->toIso8601String()),
    ));

    $this->assertApiPaginated($periodResponse);
    $periodResponse->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $portraitIndexedDuplicate->id);
});

it('returns the moderation feed using cursor pagination metadata', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    EventMedia::factory()->count(5)->create([
        'event_id' => $event->id,
    ]);

    $response = $this->apiGet('/media/feed?per_page=2');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure([
            'success',
            'data',
            'meta' => ['per_page', 'next_cursor', 'prev_cursor', 'has_more', 'stats', 'request_id'],
        ])
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.has_more', true);

    $nextCursor = $response->json('meta.next_cursor');

    expect($nextCursor)->not->toBeEmpty();

    $secondResponse = $this->apiGet('/media/feed?per_page=2&cursor='.urlencode((string) $nextCursor));

    $secondResponse->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.stats', null);
});

it('updates media favorite and pin state from moderation actions', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    $media = EventMedia::factory()->approved()->create([
        'event_id' => $event->id,
        'is_featured' => false,
        'sort_order' => 0,
    ]);

    $favoriteResponse = $this->apiPatch("/media/{$media->id}/favorite", [
        'is_featured' => true,
    ]);

    $this->assertApiSuccess($favoriteResponse);
    $favoriteResponse->assertJsonPath('data.is_featured', true);

    $pinResponse = $this->apiPatch("/media/{$media->id}/pin", [
        'is_pinned' => true,
    ]);

    $this->assertApiSuccess($pinResponse);
    $pinResponse->assertJsonPath('data.is_pinned', true);

    $media->refresh();

    expect($media->is_featured)->toBeTrue();
    expect($media->sort_order)->toBeGreaterThan(0);

    $featuredLog = Activity::query()
        ->where('description', 'Midia destacada')
        ->latest('id')
        ->first();

    $pinLog = Activity::query()
        ->where('description', 'Midia fixada')
        ->latest('id')
        ->first();

    expect($featuredLog)->not->toBeNull();
    expect($featuredLog?->event)->toBe('media.featured_updated');
    expect($featuredLog?->subject_type)->toBe(EventMedia::class);
    expect($featuredLog?->subject_id)->toBe($media->id);
    expect($featuredLog?->properties['event_id'])->toBe($event->id);
    expect($featuredLog?->properties['attributes']['is_featured'])->toBeTrue();

    expect($pinLog)->not->toBeNull();
    expect($pinLog?->event)->toBe('media.pinned_updated');
    expect($pinLog?->subject_type)->toBe(EventMedia::class);
    expect($pinLog?->subject_id)->toBe($media->id);
    expect($pinLog?->properties['attributes']['is_pinned'])->toBeTrue();
    expect($pinLog?->properties['attributes']['sort_order'])->toBeGreaterThan(0);
});

it('updates moderation state in bulk without leaving the organization scope', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    $mediaA = EventMedia::factory()->create([
        'event_id' => $event->id,
        'moderation_status' => ModerationStatus::Pending->value,
        'is_featured' => false,
        'sort_order' => 0,
    ]);

    $mediaB = EventMedia::factory()->create([
        'event_id' => $event->id,
        'moderation_status' => ModerationStatus::Pending->value,
        'is_featured' => false,
        'sort_order' => 0,
    ]);

    $approveResponse = $this->apiPost('/media/bulk/approve', [
        'ids' => [$mediaA->id, $mediaB->id],
    ]);

    $this->assertApiSuccess($approveResponse);
    $approveResponse->assertJsonPath('data.count', 2)
        ->assertJsonPath('data.items.0.moderation_status', 'approved');

    $favoriteResponse = $this->apiPatch('/media/bulk/favorite', [
        'ids' => [$mediaA->id, $mediaB->id],
        'is_featured' => true,
    ]);

    $this->assertApiSuccess($favoriteResponse);
    $favoriteResponse->assertJsonPath('data.count', 2);

    $pinResponse = $this->apiPatch('/media/bulk/pin', [
        'ids' => [$mediaA->id, $mediaB->id],
        'is_pinned' => true,
    ]);

    $this->assertApiSuccess($pinResponse);

    $mediaA->refresh();
    $mediaB->refresh();

    expect($mediaA->moderation_status)->toBe(ModerationStatus::Approved);
    expect($mediaB->moderation_status)->toBe(ModerationStatus::Approved);
    expect($mediaA->is_featured)->toBeTrue();
    expect($mediaB->is_featured)->toBeTrue();
    expect($mediaA->sort_order)->toBeGreaterThan(0);
    expect($mediaB->sort_order)->toBeGreaterThan(0);
    expect($mediaA->sort_order)->not->toBe($mediaB->sort_order);
});

it('shows pinned media first in the public gallery', function () {
    $event = Event::factory()->active()->create();

    EventModule::query()->create([
        'event_id' => $event->id,
        'module_key' => 'live',
        'is_enabled' => true,
    ]);

    $pinnedMedia = EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'sort_order' => 9,
        'published_at' => now()->subHours(4),
    ]);

    $recentMedia = EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'sort_order' => 0,
        'published_at' => now(),
    ]);

    $response = $this->apiGet("/public/events/{$event->slug}/gallery");

    $this->assertApiPaginated($response);
    $response->assertJsonPath('data.0.id', $pinnedMedia->id)
        ->assertJsonPath('data.1.id', $recentMedia->id);
});
