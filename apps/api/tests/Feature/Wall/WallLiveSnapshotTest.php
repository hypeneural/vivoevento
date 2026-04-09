<?php

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\InboundMedia\Models\InboundMessage;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Wall\Models\EventWallSetting;

function enableWallModuleForLiveSnapshot(Event $event): void
{
    EventModule::query()->create([
        'event_id' => $event->id,
        'module_key' => 'wall',
        'is_enabled' => true,
    ]);
}

it('forbids live snapshot access when the event belongs to another organization', function () {
    [$user, $organization] = $this->actingAsManager();

    $otherOrganization = $this->createOrganization();

    $event = Event::factory()->active()->create([
        'organization_id' => $otherOrganization->id,
    ]);

    $response = $this->apiGet("/events/{$event->id}/wall/live-snapshot");

    $this->assertApiForbidden($response);
});

it('returns the current wall live snapshot using the latest online player heartbeat', function () {
    [$user, $organization] = $this->actingAsManager();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);
    enableWallModuleForLiveSnapshot($event);

    $settings = EventWallSetting::factory()->live()->create([
        'event_id' => $event->id,
        'layout' => 'auto',
        'transition_effect' => 'fade',
        'show_sender_credit' => true,
    ]);

    $message = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'whatsapp',
        'message_id' => 'msg-live-snapshot',
        'message_type' => 'image',
        'sender_phone' => '5511999990009',
        'sender_name' => 'Juliana Ribeiro',
        'status' => 'received',
        'received_at' => now()->subMinutes(2),
    ]);

    $media = EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $message->id,
        'source_type' => 'whatsapp',
        'source_label' => 'WhatsApp',
        'caption' => 'Entrada da pista',
        'width' => 1920,
        'height' => 1080,
        'published_at' => now()->subMinute(),
    ]);

    $media->variants()->create([
        'variant_key' => 'thumb',
        'disk' => 'public',
        'path' => "events/{$event->id}/variants/{$media->id}/thumb.webp",
        'mime_type' => 'image/webp',
        'width' => 320,
        'height' => 320,
        'size_bytes' => 24567,
    ]);

    $this->postJson("/api/v1/public/wall/{$settings->wall_code}/heartbeat", [
        'player_instance_id' => 'player-live-snapshot',
        'runtime_status' => 'playing',
        'connection_status' => 'connected',
        'current_item_id' => 'media_'.$media->id,
        'current_sender_key' => 'whatsapp:5511999990009',
        'ready_count' => 8,
        'loading_count' => 0,
        'error_count' => 0,
        'stale_count' => 0,
        'cache_enabled' => true,
        'persistent_storage' => 'indexeddb',
        'cache_usage_bytes' => 1048576,
        'cache_quota_bytes' => 8388608,
        'cache_hit_count' => 14,
        'cache_miss_count' => 2,
        'cache_stale_fallback_count' => 0,
        'last_sync_at' => now()->toIso8601String(),
        'last_fallback_reason' => null,
    ])->assertOk();

    $response = $this->apiGet("/events/{$event->id}/wall/live-snapshot");

    $this->assertApiSuccess($response);

    $response
        ->assertJsonPath('data.wallStatus', 'live')
        ->assertJsonPath('data.layout', 'auto')
        ->assertJsonPath('data.transitionEffect', 'fade')
        ->assertJsonPath('data.currentPlayer.playerInstanceId', 'player-live-snapshot')
        ->assertJsonPath('data.currentPlayer.healthStatus', 'healthy')
        ->assertJsonPath('data.currentItem.id', 'media_'.$media->id)
        ->assertJsonPath('data.currentItem.senderName', 'Juliana Ribeiro')
        ->assertJsonPath('data.currentItem.source', 'whatsapp')
        ->assertJsonPath('data.currentItem.caption', 'Entrada da pista')
        ->assertJsonPath('data.currentItem.layoutHint', 'cinematic')
        ->assertJsonPath('data.advancedAt', fn ($value) => filled($value));

    expect($response->json('data.currentItem.previewUrl'))
        ->toContain("events/{$event->id}/variants/{$media->id}/thumb.webp");
});

it('normalizes source and exposes video semantics for the current snapshot item', function () {
    [$user, $organization] = $this->actingAsManager();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);
    enableWallModuleForLiveSnapshot($event);

    $settings = EventWallSetting::factory()->live()->create([
        'event_id' => $event->id,
        'layout' => 'auto',
    ]);

    $message = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'upload',
        'message_id' => 'msg-live-video-semantics',
        'message_type' => 'video',
        'sender_phone' => null,
        'sender_name' => 'Equipe upload',
        'status' => 'received',
        'received_at' => now()->subMinute(),
    ]);

    $media = EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $message->id,
        'media_type' => 'video',
        'mime_type' => 'video/mp4',
        'source_type' => 'public_upload',
        'source_label' => 'Upload',
        'caption' => 'Video da pista',
        'width' => 1080,
        'height' => 1920,
        'duration_seconds' => 12,
        'video_codec' => 'h264',
        'container' => 'mp4',
        'published_at' => now()->subSeconds(20),
    ]);

    $media->variants()->create([
        'variant_key' => 'wall_video_poster',
        'disk' => 'public',
        'path' => "events/{$event->id}/variants/{$media->id}/wall_video_poster.webp",
        'mime_type' => 'image/webp',
        'width' => 720,
        'height' => 1280,
        'size_bytes' => 18567,
    ]);

    $media->variants()->create([
        'variant_key' => 'wall_video_720p',
        'disk' => 'public',
        'path' => "events/{$event->id}/variants/{$media->id}/wall_video_720p.mp4",
        'mime_type' => 'video/mp4',
        'width' => 720,
        'height' => 1280,
        'size_bytes' => 1200000,
    ]);

    $this->postJson("/api/v1/public/wall/{$settings->wall_code}/heartbeat", [
        'player_instance_id' => 'player-live-video-semantics',
        'runtime_status' => 'playing',
        'connection_status' => 'connected',
        'current_item_id' => 'media_'.$media->id,
        'current_item_started_at' => '2026-04-09T04:10:00Z',
        'current_sender_key' => 'upload:team',
        'ready_count' => 8,
        'loading_count' => 0,
        'error_count' => 0,
        'stale_count' => 0,
        'cache_enabled' => true,
        'persistent_storage' => 'indexeddb',
        'cache_usage_bytes' => 1048576,
        'cache_quota_bytes' => 8388608,
        'cache_hit_count' => 14,
        'cache_miss_count' => 2,
        'cache_stale_fallback_count' => 0,
        'last_sync_at' => now()->toIso8601String(),
        'last_fallback_reason' => null,
    ])->assertOk();

    $response = $this->apiGet("/events/{$event->id}/wall/live-snapshot");

    $this->assertApiSuccess($response);

    $response
        ->assertJsonPath('data.currentItem.id', 'media_'.$media->id)
        ->assertJsonPath('data.currentItem.source', 'upload')
        ->assertJsonPath('data.currentItem.isVideo', true)
        ->assertJsonPath('data.currentItem.durationSeconds', 12)
        ->assertJsonPath('data.currentItem.videoPolicyLabel', 'Video curto')
        ->assertJsonPath('data.currentItem.videoAdmission.state', 'eligible')
        ->assertJsonPath('data.currentItem.servedVariantKey', 'wall_video_720p')
        ->assertJsonPath('data.currentItem.previewVariantKey', 'wall_video_poster');

    expect($response->json('data.currentItem.previewUrl'))
        ->toContain("events/{$event->id}/variants/{$media->id}/wall_video_poster.webp");
});

it('preserves the advance clock while the same media stays on screen and updates it when the item changes', function () {
    [$user, $organization] = $this->actingAsManager();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);
    enableWallModuleForLiveSnapshot($event);

    $settings = EventWallSetting::factory()->live()->create([
        'event_id' => $event->id,
        'layout' => 'auto',
        'transition_effect' => 'fade',
    ]);

    $messageA = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'whatsapp',
        'message_id' => 'msg-live-clock-a',
        'message_type' => 'image',
        'sender_phone' => '5511999991001',
        'sender_name' => 'Carla',
        'status' => 'received',
        'received_at' => now()->subMinutes(2),
    ]);

    $messageB = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'whatsapp',
        'message_id' => 'msg-live-clock-b',
        'message_type' => 'image',
        'sender_phone' => '5511999991002',
        'sender_name' => 'Bruno',
        'status' => 'received',
        'received_at' => now()->subMinute(),
    ]);

    $firstMedia = EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $messageA->id,
        'source_type' => 'whatsapp',
        'source_label' => 'WhatsApp',
        'caption' => 'Primeira entrada',
        'width' => 1920,
        'height' => 1080,
        'published_at' => now()->subMinute(),
    ]);

    $secondMedia = EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $messageB->id,
        'source_type' => 'whatsapp',
        'source_label' => 'WhatsApp',
        'caption' => 'Segunda entrada',
        'width' => 1920,
        'height' => 1080,
        'published_at' => now(),
    ]);

    $this->postJson("/api/v1/public/wall/{$settings->wall_code}/heartbeat", [
        'player_instance_id' => 'player-live-clock',
        'runtime_status' => 'playing',
        'connection_status' => 'connected',
        'current_item_id' => 'media_'.$firstMedia->id,
        'current_sender_key' => 'whatsapp:5511999991001',
        'ready_count' => 8,
        'loading_count' => 0,
        'error_count' => 0,
        'stale_count' => 0,
        'cache_enabled' => true,
        'persistent_storage' => 'indexeddb',
        'cache_usage_bytes' => 1048576,
        'cache_quota_bytes' => 8388608,
        'cache_hit_count' => 14,
        'cache_miss_count' => 2,
        'cache_stale_fallback_count' => 0,
        'last_sync_at' => now()->toIso8601String(),
        'last_fallback_reason' => null,
    ])->assertOk();

    $firstSnapshot = $this->apiGet("/events/{$event->id}/wall/live-snapshot");
    $firstAdvancedAt = $firstSnapshot->json('data.advancedAt');

    expect($firstAdvancedAt)->not()->toBeNull();

    $this->travel(20)->seconds();

    $this->postJson("/api/v1/public/wall/{$settings->wall_code}/heartbeat", [
        'player_instance_id' => 'player-live-clock',
        'runtime_status' => 'playing',
        'connection_status' => 'connected',
        'current_item_id' => 'media_'.$firstMedia->id,
        'current_sender_key' => 'whatsapp:5511999991001',
        'ready_count' => 9,
        'loading_count' => 0,
        'error_count' => 0,
        'stale_count' => 0,
        'cache_enabled' => true,
        'persistent_storage' => 'indexeddb',
        'cache_usage_bytes' => 1048576,
        'cache_quota_bytes' => 8388608,
        'cache_hit_count' => 15,
        'cache_miss_count' => 2,
        'cache_stale_fallback_count' => 0,
        'last_sync_at' => now()->toIso8601String(),
        'last_fallback_reason' => null,
    ])->assertOk();

    $sameItemSnapshot = $this->apiGet("/events/{$event->id}/wall/live-snapshot");

    expect($sameItemSnapshot->json('data.advancedAt'))->toBe($firstAdvancedAt);

    $this->travel(5)->seconds();

    $this->postJson("/api/v1/public/wall/{$settings->wall_code}/heartbeat", [
        'player_instance_id' => 'player-live-clock',
        'runtime_status' => 'playing',
        'connection_status' => 'connected',
        'current_item_id' => 'media_'.$secondMedia->id,
        'current_sender_key' => 'whatsapp:5511999991002',
        'ready_count' => 9,
        'loading_count' => 0,
        'error_count' => 0,
        'stale_count' => 0,
        'cache_enabled' => true,
        'persistent_storage' => 'indexeddb',
        'cache_usage_bytes' => 1048576,
        'cache_quota_bytes' => 8388608,
        'cache_hit_count' => 16,
        'cache_miss_count' => 2,
        'cache_stale_fallback_count' => 0,
        'last_sync_at' => now()->toIso8601String(),
        'last_fallback_reason' => null,
    ])->assertOk();

    $changedItemSnapshot = $this->apiGet("/events/{$event->id}/wall/live-snapshot");

    expect($changedItemSnapshot->json('data.currentItem.id'))->toBe('media_'.$secondMedia->id)
        ->and($changedItemSnapshot->json('data.advancedAt'))->not()->toBe($firstAdvancedAt);
});

it('prefers the player provided current_item_started_at and never regresses to an older timestamp', function () {
    [$user, $organization] = $this->actingAsManager();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);
    enableWallModuleForLiveSnapshot($event);

    $settings = EventWallSetting::factory()->live()->create([
        'event_id' => $event->id,
    ]);

    $message = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'whatsapp',
        'message_id' => 'msg-authoritative-clock',
        'message_type' => 'image',
        'sender_phone' => '5511999991010',
        'sender_name' => 'Patricia',
        'status' => 'received',
        'received_at' => now()->subMinute(),
    ]);

    $media = EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $message->id,
        'source_type' => 'whatsapp',
        'source_label' => 'WhatsApp',
        'caption' => 'Entrada autoritativa',
        'width' => 1920,
        'height' => 1080,
        'published_at' => now()->subSeconds(30),
    ]);

    $firstStartedAt = '2026-04-09T03:10:05Z';
    $olderStartedAt = '2026-04-09T03:10:02Z';
    $newerStartedAt = '2026-04-09T03:10:08Z';

    $this->postJson("/api/v1/public/wall/{$settings->wall_code}/heartbeat", [
        'player_instance_id' => 'player-authoritative-clock',
        'runtime_status' => 'playing',
        'connection_status' => 'connected',
        'current_item_id' => 'media_'.$media->id,
        'current_item_started_at' => $firstStartedAt,
        'current_sender_key' => 'whatsapp:5511999991010',
        'ready_count' => 8,
        'loading_count' => 0,
        'error_count' => 0,
        'stale_count' => 0,
        'cache_enabled' => true,
        'persistent_storage' => 'indexeddb',
        'cache_usage_bytes' => 1048576,
        'cache_quota_bytes' => 8388608,
        'cache_hit_count' => 14,
        'cache_miss_count' => 2,
        'cache_stale_fallback_count' => 0,
        'last_sync_at' => now()->toIso8601String(),
        'last_fallback_reason' => null,
    ])->assertOk();

    $firstSnapshot = $this->apiGet("/events/{$event->id}/wall/live-snapshot");
    expect($firstSnapshot->json('data.advancedAt'))->toBe(\Illuminate\Support\Carbon::parse($firstStartedAt)->toIso8601String());

    $this->postJson("/api/v1/public/wall/{$settings->wall_code}/heartbeat", [
        'player_instance_id' => 'player-authoritative-clock',
        'runtime_status' => 'playing',
        'connection_status' => 'connected',
        'current_item_id' => 'media_'.$media->id,
        'current_item_started_at' => $olderStartedAt,
        'current_sender_key' => 'whatsapp:5511999991010',
        'ready_count' => 9,
        'loading_count' => 0,
        'error_count' => 0,
        'stale_count' => 0,
        'cache_enabled' => true,
        'persistent_storage' => 'indexeddb',
        'cache_usage_bytes' => 1048576,
        'cache_quota_bytes' => 8388608,
        'cache_hit_count' => 15,
        'cache_miss_count' => 2,
        'cache_stale_fallback_count' => 0,
        'last_sync_at' => now()->toIso8601String(),
        'last_fallback_reason' => null,
    ])->assertOk();

    $olderSnapshot = $this->apiGet("/events/{$event->id}/wall/live-snapshot");
    expect($olderSnapshot->json('data.advancedAt'))->toBe(\Illuminate\Support\Carbon::parse($firstStartedAt)->toIso8601String());

    $this->postJson("/api/v1/public/wall/{$settings->wall_code}/heartbeat", [
        'player_instance_id' => 'player-authoritative-clock',
        'runtime_status' => 'playing',
        'connection_status' => 'connected',
        'current_item_id' => 'media_'.$media->id,
        'current_item_started_at' => $newerStartedAt,
        'current_sender_key' => 'whatsapp:5511999991010',
        'ready_count' => 10,
        'loading_count' => 0,
        'error_count' => 0,
        'stale_count' => 0,
        'cache_enabled' => true,
        'persistent_storage' => 'indexeddb',
        'cache_usage_bytes' => 1048576,
        'cache_quota_bytes' => 8388608,
        'cache_hit_count' => 16,
        'cache_miss_count' => 2,
        'cache_stale_fallback_count' => 0,
        'last_sync_at' => now()->toIso8601String(),
        'last_fallback_reason' => null,
    ])->assertOk();

    $newerSnapshot = $this->apiGet("/events/{$event->id}/wall/live-snapshot");
    expect($newerSnapshot->json('data.advancedAt'))->toBe(\Illuminate\Support\Carbon::parse($newerStartedAt)->toIso8601String());
});

it('returns nextItem when the current wall item matches the first predicted item from the real queue', function () {
    [$user, $organization] = $this->actingAsManager();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);
    enableWallModuleForLiveSnapshot($event);

    $settings = EventWallSetting::factory()->live()->create([
        'event_id' => $event->id,
        'layout' => 'auto',
        'queue_limit' => 50,
        'selection_mode' => 'balanced',
        'event_phase' => 'flow',
        'interval_ms' => 8000,
    ]);

    $currentMessage = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'whatsapp',
        'message_id' => 'msg-next-current',
        'message_type' => 'image',
        'sender_phone' => '5511999991020',
        'sender_name' => 'Aline',
        'status' => 'received',
        'received_at' => now()->subMinutes(3),
    ]);

    $nextMessage = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'upload',
        'message_id' => 'msg-next-upcoming',
        'message_type' => 'video',
        'sender_phone' => null,
        'sender_name' => 'Bruno',
        'status' => 'received',
        'received_at' => now()->subMinutes(2),
    ]);

    $currentMedia = EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $currentMessage->id,
        'source_type' => 'whatsapp',
        'source_label' => 'WhatsApp',
        'caption' => 'Agora na pista',
        'width' => 1920,
        'height' => 1080,
        'published_at' => now()->subMinute(),
    ]);

    $nextMedia = EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $nextMessage->id,
        'media_type' => 'video',
        'mime_type' => 'video/mp4',
        'source_type' => 'public_upload',
        'source_label' => 'Upload',
        'caption' => 'Proxima da fila',
        'width' => 1080,
        'height' => 1350,
        'duration_seconds' => 18,
        'video_codec' => 'h264',
        'container' => 'mp4',
        'published_at' => now()->subMinutes(2),
    ]);

    $currentMedia->variants()->create([
        'variant_key' => 'thumb',
        'disk' => 'public',
        'path' => "events/{$event->id}/variants/{$currentMedia->id}/thumb.webp",
        'mime_type' => 'image/webp',
        'width' => 320,
        'height' => 320,
        'size_bytes' => 24567,
    ]);

    $nextMedia->variants()->create([
        'variant_key' => 'wall_video_poster',
        'disk' => 'public',
        'path' => "events/{$event->id}/variants/{$nextMedia->id}/wall_video_poster.webp",
        'mime_type' => 'image/webp',
        'width' => 320,
        'height' => 320,
        'size_bytes' => 24567,
    ]);

    $nextMedia->variants()->create([
        'variant_key' => 'wall_video_720p',
        'disk' => 'public',
        'path' => "events/{$event->id}/variants/{$nextMedia->id}/wall_video_720p.mp4",
        'mime_type' => 'video/mp4',
        'width' => 720,
        'height' => 1280,
        'size_bytes' => 1200000,
    ]);

    $this->postJson("/api/v1/public/wall/{$settings->wall_code}/heartbeat", [
        'player_instance_id' => 'player-next-item',
        'runtime_status' => 'playing',
        'connection_status' => 'connected',
        'current_item_id' => 'media_'.$currentMedia->id,
        'current_item_started_at' => now()->subSeconds(4)->toIso8601String(),
        'current_sender_key' => 'whatsapp:5511999991020',
        'ready_count' => 8,
        'loading_count' => 0,
        'error_count' => 0,
        'stale_count' => 0,
        'cache_enabled' => true,
        'persistent_storage' => 'indexeddb',
        'cache_usage_bytes' => 1048576,
        'cache_quota_bytes' => 8388608,
        'cache_hit_count' => 14,
        'cache_miss_count' => 2,
        'cache_stale_fallback_count' => 0,
        'last_sync_at' => now()->toIso8601String(),
        'last_fallback_reason' => null,
    ])->assertOk();

    $response = $this->apiGet("/events/{$event->id}/wall/live-snapshot");

    $this->assertApiSuccess($response);

    $response
        ->assertJsonPath('data.currentItem.id', 'media_'.$currentMedia->id)
        ->assertJsonPath('data.nextItem.id', 'media_'.$nextMedia->id)
        ->assertJsonPath('data.nextItem.senderName', 'Bruno')
        ->assertJsonPath('data.nextItem.source', 'upload')
        ->assertJsonPath('data.nextItem.caption', 'Proxima da fila')
        ->assertJsonPath('data.nextItem.layoutHint', 'split')
        ->assertJsonPath('data.nextItem.isVideo', true)
        ->assertJsonPath('data.nextItem.durationSeconds', 18)
        ->assertJsonPath('data.nextItem.videoPolicyLabel', 'Video com duracao diferenciada')
        ->assertJsonPath('data.nextItem.videoAdmission.state', 'eligible')
        ->assertJsonPath('data.nextItem.servedVariantKey', 'wall_video_720p')
        ->assertJsonPath('data.nextItem.previewVariantKey', 'wall_video_poster');
});

it('returns nextItem as null when the backend cannot assert the next media with confidence', function () {
    [$user, $organization] = $this->actingAsManager();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);
    enableWallModuleForLiveSnapshot($event);

    $settings = EventWallSetting::factory()->live()->create([
        'event_id' => $event->id,
        'layout' => 'auto',
        'queue_limit' => 50,
        'selection_mode' => 'balanced',
        'event_phase' => 'flow',
        'interval_ms' => 8000,
    ]);

    $messageA = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'whatsapp',
        'message_id' => 'msg-next-null-a',
        'message_type' => 'image',
        'sender_phone' => '5511999991030',
        'sender_name' => 'Marina',
        'status' => 'received',
        'received_at' => now()->subMinutes(3),
    ]);

    $messageB = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'whatsapp',
        'message_id' => 'msg-next-null-b',
        'message_type' => 'image',
        'sender_phone' => '5511999991031',
        'sender_name' => 'Rafael',
        'status' => 'received',
        'received_at' => now()->subMinutes(2),
    ]);

    $predictedFirst = EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $messageA->id,
        'source_type' => 'whatsapp',
        'source_label' => 'WhatsApp',
        'caption' => 'Item previsto primeiro',
        'width' => 1920,
        'height' => 1080,
        'published_at' => now()->subMinute(),
    ]);

    $otherMedia = EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $messageB->id,
        'source_type' => 'whatsapp',
        'source_label' => 'WhatsApp',
        'caption' => 'Item real diferente',
        'width' => 1080,
        'height' => 1350,
        'published_at' => now()->subMinutes(2),
    ]);

    $this->postJson("/api/v1/public/wall/{$settings->wall_code}/heartbeat", [
        'player_instance_id' => 'player-next-null',
        'runtime_status' => 'playing',
        'connection_status' => 'connected',
        'current_item_id' => 'media_'.$otherMedia->id,
        'current_item_started_at' => now()->subSeconds(4)->toIso8601String(),
        'current_sender_key' => 'whatsapp:5511999991031',
        'ready_count' => 8,
        'loading_count' => 0,
        'error_count' => 0,
        'stale_count' => 0,
        'cache_enabled' => true,
        'persistent_storage' => 'indexeddb',
        'cache_usage_bytes' => 1048576,
        'cache_quota_bytes' => 8388608,
        'cache_hit_count' => 14,
        'cache_miss_count' => 2,
        'cache_stale_fallback_count' => 0,
        'last_sync_at' => now()->toIso8601String(),
        'last_fallback_reason' => null,
    ])->assertOk();

    $response = $this->apiGet("/events/{$event->id}/wall/live-snapshot");

    $this->assertApiSuccess($response);

    $response
        ->assertJsonPath('data.currentItem.id', 'media_'.$otherMedia->id)
        ->assertJsonPath('data.nextItem', null);
});
