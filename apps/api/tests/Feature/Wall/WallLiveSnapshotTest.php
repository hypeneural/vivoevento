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
