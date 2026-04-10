<?php

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\Events\Models\EventMediaSenderBlacklist;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\InboundMedia\Models\InboundMessage;
use App\Modules\MediaProcessing\Enums\MediaDecisionSource;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Events\MediaHidden;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Support\Facades\Event as EventFacade;
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

    collect(range(1, 5))->each(function (int $offset) use ($event) {
        EventMedia::factory()->create([
            'event_id' => $event->id,
            'sort_order' => 0,
            'created_at' => now()->subSeconds($offset),
        ]);
    });

    $response = $this->apiGet('/media/feed?per_page=2');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure([
            'success',
            'data' => [[
                'id',
                'thumbnail_url',
                'thumbnail_source',
                'preview_url',
                'preview_source',
                'moderation_thumbnail_url',
                'moderation_thumbnail_source',
                'moderation_preview_url',
                'moderation_preview_source',
                'updated_at',
            ]],
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

it('returns moderation stats from a dedicated endpoint aligned to the active filter', function () {
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

    EventMedia::factory()->create([
        'event_id' => $event->id,
        'moderation_status' => ModerationStatus::Pending->value,
    ]);

    EventMedia::factory()->approved()->create([
        'event_id' => $event->id,
        'publication_status' => \App\Modules\MediaProcessing\Enums\PublicationStatus::Published->value,
        'safety_status' => 'pass',
        'vlm_status' => 'completed',
    ]);

    EventMedia::factory()->approved()->create([
        'event_id' => $event->id,
        'safety_status' => 'review',
        'vlm_status' => 'skipped',
    ]);

    EventMedia::factory()->create([
        'event_id' => $event->id,
        'moderation_status' => ModerationStatus::Rejected->value,
        'safety_status' => 'pass',
        'vlm_status' => 'completed',
    ]);

    EventMedia::factory()->approved()->create([
        'event_id' => $event->id,
        'is_featured' => true,
        'sort_order' => 7,
        'safety_status' => 'pass',
        'vlm_status' => 'completed',
    ]);

    $response = $this->apiGet('/media/feed/stats');

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.total', 5)
        ->assertJsonPath('data.pending', 2)
        ->assertJsonPath('data.approved', 2)
        ->assertJsonPath('data.rejected', 1)
        ->assertJsonPath('data.featured', 1)
        ->assertJsonPath('data.pinned', 1);

    $pendingResponse = $this->apiGet('/media/feed/stats?status=pending_moderation');

    $this->assertApiSuccess($pendingResponse);
    $pendingResponse->assertJsonPath('data.total', 2)
        ->assertJsonPath('data.pending', 2)
        ->assertJsonPath('data.approved', 0)
        ->assertJsonPath('data.rejected', 0);
});

it('filters the moderation feed and stats by media type, duplicate cluster, ai review and error state', function () {
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

    \Database\Factories\EventMediaIntelligenceSettingFactory::new()->gate()->create([
        'event_id' => $event->id,
        'enabled' => true,
    ]);

    $aiReviewImage = EventMedia::factory()->approved()->create([
        'event_id' => $event->id,
        'media_type' => 'image',
        'safety_status' => 'review',
        'vlm_status' => 'skipped',
        'created_at' => now()->subMinutes(3),
    ]);

    $duplicateImage = EventMedia::factory()->create([
        'event_id' => $event->id,
        'media_type' => 'image',
        'duplicate_group_key' => 'dup-filter-1',
        'safety_status' => 'pass',
        'vlm_status' => 'completed',
        'created_at' => now()->subMinutes(2),
    ]);

    $videoItem = EventMedia::factory()->approved()->create([
        'event_id' => $event->id,
        'media_type' => 'video',
        'mime_type' => 'video/mp4',
        'safety_status' => 'skipped',
        'vlm_status' => 'skipped',
        'created_at' => now()->subMinute(),
    ]);

    $errorItem = EventMedia::factory()->create([
        'event_id' => $event->id,
        'media_type' => 'image',
        'processing_status' => 'failed',
        'moderation_status' => null,
        'publication_status' => null,
        'safety_status' => 'skipped',
        'vlm_status' => 'skipped',
    ]);

    $imagesFeed = $this->apiGet('/media/feed?per_page=20&media_type=image');
    $imagesFeed->assertOk();
    expect(collect($imagesFeed->json('data'))->pluck('id')->all())
        ->toContain($aiReviewImage->id, $duplicateImage->id, $errorItem->id)
        ->not->toContain($videoItem->id);

    $videosFeed = $this->apiGet('/media/feed?per_page=20&media_type=video');
    $videosFeed->assertOk();
    expect(collect($videosFeed->json('data'))->pluck('id')->all())
        ->toEqual([$videoItem->id]);

    $duplicatesFeed = $this->apiGet('/media/feed?per_page=20&duplicates=1');
    $duplicatesFeed->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $duplicateImage->id);

    $aiReviewFeed = $this->apiGet('/media/feed?per_page=20&ai_review=1');
    $aiReviewFeed->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $aiReviewImage->id)
        ->assertJsonPath('data.0.status', 'pending_moderation');

    $errorFeed = $this->apiGet('/media/feed?per_page=20&status=error');
    $errorFeed->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $errorItem->id)
        ->assertJsonPath('data.0.status', 'error');

    $aiReviewStats = $this->apiGet('/media/feed/stats?ai_review=1');
    $this->assertApiSuccess($aiReviewStats);
    $aiReviewStats->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.pending', 1)
        ->assertJsonPath('data.approved', 0)
        ->assertJsonPath('data.rejected', 0);

    $videoStats = $this->apiGet('/media/feed/stats?media_type=video');
    $this->assertApiSuccess($videoStats);
    $videoStats->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.approved', 1);
});

it('searches the moderation feed across event title and sender identity fields', function () {
    [$user, $organization] = $this->actingAsOwner();

    $matchingEvent = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'title' => 'Festival da Identidade',
    ]);

    $matchingInbound = InboundMessage::query()->create([
        'event_id' => $matchingEvent->id,
        'provider' => 'zapi',
        'message_id' => 'search-001',
        'message_type' => 'image',
        'chat_external_id' => '120363499999999999-group',
        'sender_external_id' => 'sender-ana-001',
        'sender_phone' => '554899991111',
        'sender_lid' => 'ana-001@lid',
        'sender_name' => 'Ana Martins',
        'status' => 'processed',
        'received_at' => now()->subMinutes(10),
    ]);

    $matchingMedia = EventMedia::factory()->create([
        'event_id' => $matchingEvent->id,
        'inbound_message_id' => $matchingInbound->id,
        'caption' => 'Entrada principal',
        'source_label' => 'Ana Martins',
    ]);

    $otherEvent = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'title' => 'Outro Evento',
    ]);

    $otherInbound = InboundMessage::query()->create([
        'event_id' => $otherEvent->id,
        'provider' => 'zapi',
        'message_id' => 'search-002',
        'message_type' => 'image',
        'chat_external_id' => '554899992222',
        'sender_external_id' => 'sender-caio-001',
        'sender_phone' => '554899992222',
        'sender_lid' => 'caio-001@lid',
        'sender_name' => 'Caio Souza',
        'status' => 'processed',
        'received_at' => now()->subMinutes(9),
    ]);

    EventMedia::factory()->create([
        'event_id' => $otherEvent->id,
        'inbound_message_id' => $otherInbound->id,
        'caption' => 'Outro registro',
        'source_label' => 'Caio Souza',
    ]);

    $eventSearch = $this->apiGet('/media/feed?per_page=10&search='.urlencode('Festival da Identidade'));
    $eventSearch->assertOk();

    expect(collect($eventSearch->json('data'))->pluck('id')->all())
        ->toContain($matchingMedia->id);

    $senderNameSearch = $this->apiGet('/media/feed?per_page=10&search='.urlencode('Ana Martins'));
    $senderNameSearch->assertOk();

    expect(collect($senderNameSearch->json('data'))->pluck('id')->all())
        ->toContain($matchingMedia->id);

    $senderIdentitySearch = $this->apiGet('/media/feed?per_page=10&search='.urlencode('sender-ana-001'));
    $senderIdentitySearch->assertOk();

    expect(collect($senderIdentitySearch->json('data'))->pluck('id')->all())
        ->toContain($matchingMedia->id);
});

it('treats whatsapp group and direct sources as the whatsapp channel in catalog filters', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    $groupMedia = EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'source_type' => 'whatsapp_group',
    ]);

    $directMedia = EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'source_type' => 'whatsapp_direct',
    ]);

    EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'source_type' => 'public_upload',
    ]);

    $response = $this->apiGet("/gallery?event_id={$event->id}&channel=whatsapp&publication_status=published");

    $this->assertApiPaginated($response);

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($groupMedia->id, $directMedia->id)
        ->and($ids)->toHaveCount(2)
        ->and($response->json('data.0.channel'))->toBe('whatsapp')
        ->and($response->json('data.1.channel'))->toBe('whatsapp');
});

it('returns sender moderation context in the feed and filters blocked senders', function () {
    [$user, $organization] = $this->actingAsOwner();

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

    $blockedInbound = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'zapi',
        'message_id' => 'blocked-001',
        'message_type' => 'image',
        'chat_external_id' => '120363499999999999-group',
        'sender_external_id' => '11111111111111@lid',
        'sender_phone' => '554899991111',
        'sender_lid' => '11111111111111@lid',
        'sender_name' => 'Ana Martins',
        'sender_avatar_url' => 'https://cdn.eventovivo.test/ana.jpg',
        'status' => 'processed',
        'received_at' => now()->subMinutes(10),
    ]);

    $blockedMedia = EventMedia::factory()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $blockedInbound->id,
        'source_type' => 'whatsapp_group',
        'source_label' => 'Ana Martins',
    ]);

    EventMedia::factory()->create([
        'event_id' => $event->id,
        'inbound_message_id' => InboundMessage::query()->create([
            'event_id' => $event->id,
            'provider' => 'zapi',
            'message_id' => 'blocked-002',
            'message_type' => 'image',
            'chat_external_id' => '120363499999999999-group',
            'sender_external_id' => '11111111111111@lid',
            'sender_phone' => '554899991111',
            'sender_lid' => '11111111111111@lid',
            'sender_name' => 'Ana Martins',
            'status' => 'processed',
            'received_at' => now()->subMinutes(9),
        ])->id,
        'source_type' => 'whatsapp_group',
        'source_label' => 'Ana Martins',
    ]);

    $unblockedInbound = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'zapi',
        'message_id' => 'open-001',
        'message_type' => 'image',
        'chat_external_id' => '554899992222',
        'sender_external_id' => '554899992222',
        'sender_phone' => '554899992222',
        'sender_name' => 'Caio Souza',
        'status' => 'processed',
        'received_at' => now()->subMinutes(8),
    ]);

    EventMedia::factory()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $unblockedInbound->id,
        'source_type' => 'whatsapp_direct',
        'source_label' => 'Caio Souza',
    ]);

    EventMediaSenderBlacklist::factory()->create([
        'event_id' => $event->id,
        'identity_type' => 'lid',
        'identity_value' => '11111111111111@lid',
        'normalized_phone' => null,
        'is_active' => true,
    ]);

    $feedResponse = $this->apiGet('/media/feed?per_page=10');

    $feedResponse->assertOk();

    $feedItems = collect($feedResponse->json('data'));
    $blockedItem = $feedItems->firstWhere('id', $blockedMedia->id);

    expect($blockedItem)->not->toBeNull()
        ->and($blockedItem['sender_name'])->toBe('Ana Martins')
        ->and($blockedItem['sender_blocked'])->toBeTrue()
        ->and($blockedItem['sender_blacklist_enabled'])->toBeTrue()
        ->and($blockedItem['sender_recommended_identity_type'])->toBe('lid')
        ->and($blockedItem['sender_avatar_url'])->toBe('https://cdn.eventovivo.test/ana.jpg');

    $blockedOnlyResponse = $this->apiGet('/media/feed?per_page=10&sender_blocked=1');

    $blockedOnlyResponse->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.sender_blocked', true)
        ->assertJsonPath('data.1.sender_blocked', true);

    $detailResponse = $this->apiGet("/media/{$blockedMedia->id}");

    $this->assertApiSuccess($detailResponse);
    $detailResponse->assertJsonPath('data.sender_media_count', 2)
        ->assertJsonPath('data.sender_blocked', true);
});

it('blocks and unblocks a sender directly from moderation actions', function () {
    [$user, $organization] = $this->actingAsOwner();

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
        'message_id' => 'block-action-001',
        'message_type' => 'image',
        'chat_external_id' => '120363499999999999-group',
        'sender_external_id' => '11111111111111@lid',
        'sender_phone' => '554899991111',
        'sender_lid' => '11111111111111@lid',
        'sender_name' => 'Ana Martins',
        'status' => 'processed',
        'received_at' => now()->subMinutes(5),
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $inbound->id,
        'source_type' => 'whatsapp_group',
        'source_label' => 'Ana Martins',
    ]);

    $expiresAt = now()->addHours(6)->toIso8601String();

    $blockResponse = $this->apiPost("/media/{$media->id}/sender-block", [
        'reason' => 'Bloqueado pela equipe de moderacao',
        'expires_at' => $expiresAt,
    ]);

    $this->assertApiSuccess($blockResponse);
    $blockResponse->assertJsonPath('data.sender_blocked', true)
        ->assertJsonPath('data.sender_block_reason', 'Bloqueado pela equipe de moderacao')
        ->assertJsonPath('data.sender_recommended_identity_type', 'lid')
        ->assertJsonPath('data.sender_media_count', 1);

    $entry = EventMediaSenderBlacklist::query()->where('event_id', $event->id)->sole();

    expect($entry->identity_type)->toBe('lid')
        ->and($entry->identity_value)->toBe('11111111111111@lid')
        ->and($entry->is_active)->toBeTrue();

    $unblockResponse = $this->apiDelete("/media/{$media->id}/sender-block");

    $unblockResponse->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.sender_blocked', false)
        ->assertJsonPath('data.sender_blocking_entry_id', null);

    expect($entry->fresh()->is_active)->toBeFalse();
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

it('lists the duplicate cluster for a moderation item within organization scope', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    $sameGroupA = EventMedia::factory()->create([
        'event_id' => $event->id,
        'duplicate_group_key' => 'dup-cluster-1',
        'created_at' => now()->subMinutes(2),
    ]);

    $sameGroupB = EventMedia::factory()->create([
        'event_id' => $event->id,
        'duplicate_group_key' => 'dup-cluster-1',
        'created_at' => now()->subMinute(),
    ]);

    EventMedia::factory()->create([
        'event_id' => $event->id,
        'duplicate_group_key' => 'dup-cluster-2',
    ]);

    EventMedia::factory()->create([
        'event_id' => Event::factory()->active()->create()->id,
        'duplicate_group_key' => 'dup-cluster-1',
    ]);

    $response = $this->apiGet("/media/{$sameGroupA->id}/duplicates");

    $this->assertApiSuccess($response);
    $response->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $sameGroupB->id)
        ->assertJsonPath('data.1.id', $sameGroupA->id)
        ->assertJsonPath('data.0.duplicate_group_key', 'dup-cluster-1');
});

it('undoes a manual moderation decision back to pending review', function () {
    [$user, $organization] = $this->actingAsOwner();
    EventFacade::fake([MediaHidden::class]);

    $event = Event::factory()->active()->manualModeration()->create([
        'organization_id' => $organization->id,
    ]);

    $media = EventMedia::factory()->approved()->create([
        'event_id' => $event->id,
        'publication_status' => PublicationStatus::Published->value,
        'published_at' => now(),
        'decision_source' => MediaDecisionSource::UserOverride->value,
        'decision_overridden_at' => now(),
        'decision_overridden_by_user_id' => $user->id,
        'decision_override_reason' => 'Aprovada manualmente',
    ]);

    $response = $this->apiPost("/media/{$media->id}/undo-decision");

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.id', $media->id)
        ->assertJsonPath('data.status', 'pending_moderation')
        ->assertJsonPath('data.moderation_status', 'pending')
        ->assertJsonPath('data.publication_status', 'draft')
        ->assertJsonPath('data.decision_source', null)
        ->assertJsonPath('data.decision_override_reason', null)
        ->assertJsonPath('data.published_at', null);

    $media->refresh();

    expect($media->moderation_status)->toBe(ModerationStatus::Pending)
        ->and($media->publication_status)->toBe(PublicationStatus::Draft)
        ->and($media->decision_source)->toBeNull()
        ->and($media->decision_override_reason)->toBeNull()
        ->and($media->published_at)->toBeNull();

    EventFacade::assertDispatched(MediaHidden::class);
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
