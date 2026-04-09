<?php

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\InboundMedia\Models\InboundMessage;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Wall\Models\EventWallSetting;

function enableWallModuleForInsights(Event $event): void
{
    EventModule::query()->create([
        'event_id' => $event->id,
        'module_key' => 'wall',
        'is_enabled' => true,
    ]);
}

it('forbids wall insights access when the event belongs to another organization', function () {
    [$user, $organization] = $this->actingAsManager();

    $otherOrganization = $this->createOrganization();

    $event = Event::factory()->active()->create([
        'organization_id' => $otherOrganization->id,
    ]);

    $response = $this->apiGet("/events/{$event->id}/wall/insights");

    $this->assertApiForbidden($response);
});

it('returns an empty wall insights payload when the event has no media yet', function () {
    [$user, $organization] = $this->actingAsManager();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);
    enableWallModuleForInsights($event);

    EventWallSetting::factory()->live()->create([
        'event_id' => $event->id,
    ]);

    $response = $this->apiGet("/events/{$event->id}/wall/insights");

    $this->assertApiSuccess($response);

    $response
        ->assertJsonPath('data.topContributor', null)
        ->assertJsonPath('data.totals.received', 0)
        ->assertJsonPath('data.totals.approved', 0)
        ->assertJsonPath('data.totals.queued', 0)
        ->assertJsonPath('data.totals.displayed', 0)
        ->assertJsonPath('data.recentItems', [])
        ->assertJsonPath('data.sourceMix', [])
        ->assertJsonPath('data.lastCaptureAt', null);
});

it('returns top contributor totals recent items and source mix for wall insights', function () {
    [$user, $organization] = $this->actingAsManager();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);
    enableWallModuleForInsights($event);

    EventWallSetting::factory()->live()->create([
        'event_id' => $event->id,
    ]);

    $anaA = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'whatsapp',
        'message_id' => 'msg-ana-a',
        'message_type' => 'image',
        'sender_phone' => '5511999990001',
        'sender_name' => 'Ana',
        'status' => 'received',
        'received_at' => now()->subMinutes(10),
    ]);

    $anaB = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'whatsapp',
        'message_id' => 'msg-ana-b',
        'message_type' => 'image',
        'sender_phone' => '5511999990001',
        'sender_name' => 'Ana',
        'status' => 'received',
        'received_at' => now()->subMinutes(6),
    ]);

    $bruno = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'whatsapp',
        'message_id' => 'msg-bruno',
        'message_type' => 'image',
        'sender_phone' => '5511999990002',
        'sender_name' => 'Bruno',
        'status' => 'received',
        'received_at' => now()->subMinutes(4),
    ]);

    $anaPublished = EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $anaA->id,
        'source_type' => 'whatsapp',
        'source_label' => 'WhatsApp',
    ]);
    $anaPublished->forceFill([
        'created_at' => now()->subMinutes(9),
        'published_at' => now()->subMinutes(8),
    ])->saveQuietly();
    $anaPublished->variants()->create([
        'variant_key' => 'thumb',
        'disk' => 'public',
        'path' => 'events/'.$event->id.'/thumbs/ana-published.jpg',
        'width' => 320,
        'height' => 320,
        'size_bytes' => 10240,
        'mime_type' => 'image/jpeg',
    ]);

    $anaApproved = EventMedia::factory()->approved()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $anaB->id,
        'source_type' => 'whatsapp',
        'source_label' => 'WhatsApp',
    ]);
    $anaApproved->forceFill([
        'created_at' => now()->subMinutes(5),
    ])->saveQuietly();
    $anaApproved->variants()->create([
        'variant_key' => 'thumb',
        'disk' => 'public',
        'path' => 'events/'.$event->id.'/thumbs/ana-approved.jpg',
        'width' => 320,
        'height' => 320,
        'size_bytes' => 10240,
        'mime_type' => 'image/jpeg',
    ]);

    $brunoPublished = EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $bruno->id,
        'source_type' => 'whatsapp',
        'source_label' => 'WhatsApp',
    ]);
    $brunoPublished->forceFill([
        'created_at' => now()->subMinutes(3),
        'published_at' => now()->subMinutes(2),
    ])->saveQuietly();
    $brunoPublished->variants()->create([
        'variant_key' => 'thumb',
        'disk' => 'public',
        'path' => 'events/'.$event->id.'/thumbs/bruno-published.jpg',
        'width' => 320,
        'height' => 320,
        'size_bytes' => 10240,
        'mime_type' => 'image/jpeg',
    ]);

    $uploadMedia = EventMedia::factory()->create([
        'event_id' => $event->id,
        'media_type' => 'image',
        'source_type' => 'public_upload',
        'source_label' => 'Upload',
        'moderation_status' => 'rejected',
        'publication_status' => 'draft',
    ]);
    $uploadMedia->forceFill([
        'created_at' => now()->subMinute(),
    ])->saveQuietly();
    $uploadMedia->variants()->create([
        'variant_key' => 'thumb',
        'disk' => 'public',
        'path' => 'events/'.$event->id.'/thumbs/upload-rejected.jpg',
        'width' => 320,
        'height' => 320,
        'size_bytes' => 10240,
        'mime_type' => 'image/jpeg',
    ]);

    $response = $this->apiGet("/events/{$event->id}/wall/insights");

    $this->assertApiSuccess($response);

    $response
        ->assertJsonPath('data.topContributor.displayName', 'Ana')
        ->assertJsonPath('data.topContributor.mediaCount', 2)
        ->assertJsonPath('data.topContributor.source', 'whatsapp')
        ->assertJsonPath('data.totals.received', 4)
        ->assertJsonPath('data.totals.approved', 3)
        ->assertJsonPath('data.totals.queued', 2)
        ->assertJsonPath('data.totals.displayed', 0)
        ->assertJsonPath('data.recentItems.0.id', (string) $uploadMedia->id)
        ->assertJsonPath('data.recentItems.0.senderName', 'Upload')
        ->assertJsonPath('data.recentItems.0.source', 'upload')
        ->assertJsonPath('data.recentItems.0.status', 'error')
        ->assertJsonPath('data.recentItems.1.id', (string) $brunoPublished->id)
        ->assertJsonPath('data.recentItems.1.senderName', 'Bruno')
        ->assertJsonPath('data.recentItems.1.source', 'whatsapp')
        ->assertJsonPath('data.recentItems.1.status', 'queued')
        ->assertJsonPath('data.lastCaptureAt', $uploadMedia->created_at?->toIso8601String());

    $payload = $response->json('data');

    expect($payload['recentItems'][0]['previewUrl'])->toContain('events/'.$event->id.'/thumbs/upload-rejected.jpg')
        ->and(collect($payload['sourceMix'])->firstWhere('source', 'whatsapp')['count'] ?? null)->toBe(3)
        ->and(collect($payload['sourceMix'])->firstWhere('source', 'upload')['count'] ?? null)->toBe(1);
});

it('increments displayed totals when the wall advances and counts a replay of the same media later', function () {
    [$user, $organization] = $this->actingAsManager();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);
    enableWallModuleForInsights($event);

    $settings = EventWallSetting::factory()->live()->create([
        'event_id' => $event->id,
        'interval_ms' => 8000,
    ]);

    $messageA = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'whatsapp',
        'message_id' => 'msg-displayed-a',
        'message_type' => 'image',
        'sender_phone' => '5511999991101',
        'sender_name' => 'Aline',
        'status' => 'received',
        'received_at' => now()->subMinutes(3),
    ]);

    $messageB = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'whatsapp',
        'message_id' => 'msg-displayed-b',
        'message_type' => 'image',
        'sender_phone' => '5511999991102',
        'sender_name' => 'Bruno',
        'status' => 'received',
        'received_at' => now()->subMinutes(2),
    ]);

    $firstMedia = EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $messageA->id,
        'source_type' => 'whatsapp',
        'source_label' => 'WhatsApp',
        'published_at' => now()->subMinutes(2),
    ]);

    $secondMedia = EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $messageB->id,
        'source_type' => 'whatsapp',
        'source_label' => 'WhatsApp',
        'published_at' => now()->subMinute(),
    ]);

    $this->postJson("/api/v1/public/wall/{$settings->wall_code}/heartbeat", [
        'player_instance_id' => 'player-displayed-counter',
        'runtime_status' => 'playing',
        'connection_status' => 'connected',
        'current_item_id' => 'media_'.$firstMedia->id,
        'current_item_started_at' => '2026-04-09T03:20:00Z',
        'current_sender_key' => 'whatsapp:5511999991101',
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

    $this->postJson("/api/v1/public/wall/{$settings->wall_code}/heartbeat", [
        'player_instance_id' => 'player-displayed-counter',
        'runtime_status' => 'playing',
        'connection_status' => 'connected',
        'current_item_id' => 'media_'.$secondMedia->id,
        'current_item_started_at' => '2026-04-09T03:20:08Z',
        'current_sender_key' => 'whatsapp:5511999991102',
        'ready_count' => 8,
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

    $this->postJson("/api/v1/public/wall/{$settings->wall_code}/heartbeat", [
        'player_instance_id' => 'player-displayed-counter',
        'runtime_status' => 'playing',
        'connection_status' => 'connected',
        'current_item_id' => 'media_'.$firstMedia->id,
        'current_item_started_at' => '2026-04-09T03:20:24Z',
        'current_sender_key' => 'whatsapp:5511999991101',
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

    $response = $this->apiGet("/events/{$event->id}/wall/insights");

    $this->assertApiSuccess($response);

    $response->assertJsonPath('data.totals.displayed', 3);
});

it('does not duplicate displayed totals when the same display heartbeat repeats or regresses', function () {
    [$user, $organization] = $this->actingAsManager();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);
    enableWallModuleForInsights($event);

    $settings = EventWallSetting::factory()->live()->create([
        'event_id' => $event->id,
        'interval_ms' => 8000,
    ]);

    $message = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'whatsapp',
        'message_id' => 'msg-displayed-idempotent',
        'message_type' => 'image',
        'sender_phone' => '5511999991111',
        'sender_name' => 'Clara',
        'status' => 'received',
        'received_at' => now()->subMinutes(2),
    ]);

    $media = EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $message->id,
        'source_type' => 'whatsapp',
        'source_label' => 'WhatsApp',
        'published_at' => now()->subMinute(),
    ]);

    $basePayload = [
        'player_instance_id' => 'player-idempotent-counter',
        'runtime_status' => 'playing',
        'connection_status' => 'connected',
        'current_item_id' => 'media_'.$media->id,
        'current_sender_key' => 'whatsapp:5511999991111',
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
    ];

    $this->postJson("/api/v1/public/wall/{$settings->wall_code}/heartbeat", array_merge($basePayload, [
        'current_item_started_at' => '2026-04-09T03:30:00Z',
    ]))->assertOk();

    $this->postJson("/api/v1/public/wall/{$settings->wall_code}/heartbeat", array_merge($basePayload, [
        'current_item_started_at' => '2026-04-09T03:30:00Z',
    ]))->assertOk();

    $this->postJson("/api/v1/public/wall/{$settings->wall_code}/heartbeat", array_merge($basePayload, [
        'current_item_started_at' => '2026-04-09T03:29:58Z',
    ]))->assertOk();

    $this->postJson("/api/v1/public/wall/{$settings->wall_code}/heartbeat", array_merge($basePayload, [
        'current_item_started_at' => '2026-04-09T03:30:03Z',
    ]))->assertOk();

    $response = $this->apiGet("/events/{$event->id}/wall/insights");

    $this->assertApiSuccess($response);

    $response->assertJsonPath('data.totals.displayed', 1);
});

it('isolates displayed totals per event wall', function () {
    [$user, $organization] = $this->actingAsManager();

    $firstEvent = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);
    $secondEvent = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);
    enableWallModuleForInsights($firstEvent);
    enableWallModuleForInsights($secondEvent);

    $firstSettings = EventWallSetting::factory()->live()->create([
        'event_id' => $firstEvent->id,
        'interval_ms' => 8000,
    ]);
    EventWallSetting::factory()->live()->create([
        'event_id' => $secondEvent->id,
        'interval_ms' => 8000,
    ]);

    $message = InboundMessage::query()->create([
        'event_id' => $firstEvent->id,
        'provider' => 'whatsapp',
        'message_id' => 'msg-displayed-isolated',
        'message_type' => 'image',
        'sender_phone' => '5511999991122',
        'sender_name' => 'Diego',
        'status' => 'received',
        'received_at' => now()->subMinutes(2),
    ]);

    $media = EventMedia::factory()->published()->create([
        'event_id' => $firstEvent->id,
        'inbound_message_id' => $message->id,
        'source_type' => 'whatsapp',
        'source_label' => 'WhatsApp',
        'published_at' => now()->subMinute(),
    ]);

    $this->postJson("/api/v1/public/wall/{$firstSettings->wall_code}/heartbeat", [
        'player_instance_id' => 'player-isolated-counter',
        'runtime_status' => 'playing',
        'connection_status' => 'connected',
        'current_item_id' => 'media_'.$media->id,
        'current_item_started_at' => '2026-04-09T03:40:00Z',
        'current_sender_key' => 'whatsapp:5511999991122',
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

    $firstResponse = $this->apiGet("/events/{$firstEvent->id}/wall/insights");
    $secondResponse = $this->apiGet("/events/{$secondEvent->id}/wall/insights");

    $this->assertApiSuccess($firstResponse);
    $this->assertApiSuccess($secondResponse);

    $firstResponse->assertJsonPath('data.totals.displayed', 1);
    $secondResponse->assertJsonPath('data.totals.displayed', 0);
});
