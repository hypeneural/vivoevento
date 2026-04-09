<?php

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\InboundMedia\Models\InboundMessage;
use App\Modules\Wall\Events\WallDiagnosticsUpdated;
use App\Modules\Wall\Models\EventWallSetting;
use App\Modules\Wall\Models\WallPlayerRuntimeStatus;
use Illuminate\Support\Facades\Event as EventFacade;

function enableWallModule(Event $event): void
{
    EventModule::query()->create([
        'event_id' => $event->id,
        'module_key' => 'wall',
        'is_enabled' => true,
    ]);
}

it('stores and upserts a public wall heartbeat per player instance', function () {
    $event = Event::factory()->active()->create();
    enableWallModule($event);

    $settings = EventWallSetting::factory()->live()->create([
        'event_id' => $event->id,
    ]);

    $payload = [
        'player_instance_id' => 'player-alpha',
        'runtime_status' => 'playing',
        'connection_status' => 'connected',
        'current_item_id' => 'media_10',
        'current_sender_key' => 'whatsapp:5511999999999',
        'ready_count' => 4,
        'loading_count' => 1,
        'error_count' => 0,
        'stale_count' => 0,
        'cache_enabled' => true,
        'persistent_storage' => 'localstorage',
        'cache_usage_bytes' => 1048576,
        'cache_quota_bytes' => 8388608,
        'cache_hit_count' => 12,
        'cache_miss_count' => 3,
        'cache_stale_fallback_count' => 1,
        'last_sync_at' => now()->toIso8601String(),
        'last_fallback_reason' => null,
    ];

    $response = $this->postJson("/api/v1/public/wall/{$settings->wall_code}/heartbeat", $payload);

    $response->assertOk()
        ->assertJsonPath('data.acknowledged_at', fn ($value) => filled($value));

    expect(WallPlayerRuntimeStatus::query()->count())->toBe(1)
        ->and(WallPlayerRuntimeStatus::query()->firstOrFail()->ready_count)->toBe(4);

    $secondResponse = $this->postJson("/api/v1/public/wall/{$settings->wall_code}/heartbeat", [
        ...$payload,
        'ready_count' => 6,
        'loading_count' => 0,
    ]);

    $secondResponse->assertOk();

    expect(WallPlayerRuntimeStatus::query()->count())->toBe(1)
        ->and(WallPlayerRuntimeStatus::query()->firstOrFail()->ready_count)->toBe(6)
        ->and(WallPlayerRuntimeStatus::query()->firstOrFail()->loading_count)->toBe(0)
        ->and(WallPlayerRuntimeStatus::query()->firstOrFail()->cache_hit_count)->toBe(12)
        ->and(WallPlayerRuntimeStatus::query()->firstOrFail()->cache_quota_bytes)->toBe(8388608);
});

it('returns diagnostics summary and marks a player offline after sixty seconds without heartbeat', function () {
    [$user, $organization] = $this->actingAsManager();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);
    enableWallModule($event);

    $settings = EventWallSetting::factory()->live()->create([
        'event_id' => $event->id,
    ]);

    $this->postJson("/api/v1/public/wall/{$settings->wall_code}/heartbeat", [
        'player_instance_id' => 'player-offline',
        'runtime_status' => 'playing',
        'connection_status' => 'connected',
        'current_item_id' => 'media_20',
        'current_sender_key' => 'whatsapp:5511888888888',
        'ready_count' => 3,
        'loading_count' => 0,
        'error_count' => 0,
        'stale_count' => 0,
        'cache_enabled' => true,
        'persistent_storage' => 'localstorage',
        'cache_usage_bytes' => 1048576,
        'cache_quota_bytes' => 8388608,
        'cache_hit_count' => 10,
        'cache_miss_count' => 2,
        'cache_stale_fallback_count' => 0,
        'last_sync_at' => now()->toIso8601String(),
        'last_fallback_reason' => null,
    ])->assertOk();

    $this->travel(61)->seconds();

    $response = $this->apiGet("/events/{$event->id}/wall/diagnostics");

    $this->assertApiSuccess($response);

    $response->assertJsonPath('data.summary.health_status', 'offline')
        ->assertJsonPath('data.summary.total_players', 1)
        ->assertJsonPath('data.summary.online_players', 0)
        ->assertJsonPath('data.summary.offline_players', 1)
        ->assertJsonPath('data.summary.cache_hit_rate_avg', 83)
        ->assertJsonPath('data.summary.cache_usage_bytes_max', 1048576)
        ->assertJsonPath('data.summary.cache_quota_bytes_max', 8388608)
        ->assertJsonPath('data.summary.cache_stale_fallback_count', 0)
        ->assertJsonPath('data.players.0.health_status', 'offline')
        ->assertJsonPath('data.players.0.is_online', false)
        ->assertJsonPath('data.players.0.cache_hit_rate', 83)
        ->assertJsonPath('data.players.0.cache_usage_bytes', 1048576)
        ->assertJsonPath('data.players.0.cache_quota_bytes', 8388608);
});

it('broadcasts diagnostics updates on the private event wall channel when the aggregate changes', function () {
    $event = Event::factory()->active()->create();
    enableWallModule($event);

    $settings = EventWallSetting::factory()->live()->create([
        'event_id' => $event->id,
    ]);

    EventFacade::fake([WallDiagnosticsUpdated::class]);

    $this->postJson("/api/v1/public/wall/{$settings->wall_code}/heartbeat", [
        'player_instance_id' => 'player-broadcast',
        'runtime_status' => 'playing',
        'connection_status' => 'connected',
        'current_item_id' => 'media_30',
        'current_sender_key' => 'whatsapp:5511777777777',
        'ready_count' => 2,
        'loading_count' => 0,
        'error_count' => 0,
        'stale_count' => 0,
        'cache_enabled' => true,
        'persistent_storage' => 'localstorage',
        'cache_usage_bytes' => 1048576,
        'cache_quota_bytes' => 8388608,
        'cache_hit_count' => 6,
        'cache_miss_count' => 2,
        'cache_stale_fallback_count' => 0,
        'last_sync_at' => now()->toIso8601String(),
        'last_fallback_reason' => null,
    ])->assertOk();

    EventFacade::assertDispatched(
        WallDiagnosticsUpdated::class,
        fn (WallDiagnosticsUpdated $eventPayload) => $eventPayload->eventId === $event->id
            && $eventPayload->payload['health_status'] === 'healthy',
    );
});

it('simulates the next wall slides using the real event queue and draft settings', function () {
    [$user, $organization] = $this->actingAsManager();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);
    enableWallModule($event);

    $settings = EventWallSetting::factory()->live()->create([
        'event_id' => $event->id,
        'queue_limit' => 12,
    ]);

    $messageA = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'whatsapp',
        'message_id' => 'msg-a',
        'message_type' => 'image',
        'sender_phone' => '5511999990001',
        'sender_name' => 'Ana',
        'status' => 'received',
        'received_at' => now(),
    ]);

    $messageB = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'whatsapp',
        'message_id' => 'msg-b',
        'message_type' => 'image',
        'sender_phone' => '5511999990002',
        'sender_name' => 'Bruno',
        'status' => 'received',
        'received_at' => now(),
    ]);

    $messageC = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'whatsapp',
        'message_id' => 'msg-c',
        'message_type' => 'image',
        'sender_phone' => '5511999990003',
        'sender_name' => 'Carla',
        'status' => 'received',
        'received_at' => now(),
    ]);

    \App\Modules\MediaProcessing\Models\EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $messageA->id,
        'source_type' => 'whatsapp',
        'published_at' => now()->subMinutes(3),
    ]);

    \App\Modules\MediaProcessing\Models\EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $messageA->id,
        'source_type' => 'whatsapp',
        'published_at' => now()->subMinutes(2),
    ]);

    \App\Modules\MediaProcessing\Models\EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $messageB->id,
        'source_type' => 'public_upload',
        'published_at' => now()->subMinute(),
    ]);

    $carlaPublished = \App\Modules\MediaProcessing\Models\EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $messageC->id,
        'source_type' => 'telegram',
        'caption' => 'Entrada principal no palco',
        'width' => 1920,
        'height' => 1080,
        'published_at' => now(),
    ]);

    $carlaPublished->variants()->create([
        'variant_key' => 'thumb',
        'disk' => 'public',
        'path' => "events/{$event->id}/variants/{$carlaPublished->id}/thumb.webp",
        'mime_type' => 'image/webp',
        'width' => 320,
        'height' => 320,
        'size_bytes' => 32145,
    ]);

    $response = $this->apiPost("/events/{$event->id}/wall/simulate", [
        'selection_mode' => 'balanced',
        'event_phase' => 'party',
        'selection_policy' => [
            'max_eligible_items_per_sender' => 4,
            'max_replays_per_item' => 2,
            'low_volume_max_items' => 6,
            'medium_volume_max_items' => 12,
            'replay_interval_low_minutes' => 8,
            'replay_interval_medium_minutes' => 12,
            'replay_interval_high_minutes' => 20,
            'sender_cooldown_seconds' => 60,
            'sender_window_limit' => 3,
            'sender_window_minutes' => 10,
            'avoid_same_sender_if_alternative_exists' => true,
            'avoid_same_duplicate_cluster_if_alternative_exists' => true,
        ],
        'interval_ms' => 10000,
    ]);

    $this->assertApiSuccess($response);

    $response->assertJsonPath('data.summary.selection_mode', 'balanced')
        ->assertJsonPath('data.summary.event_phase', 'party')
        ->assertJsonPath('data.summary.event_phase_label', 'Festa')
        ->assertJsonPath('data.summary.active_senders', 3)
        ->assertJsonPath('data.sequence_preview.0.sender_name', 'Carla')
        ->assertJsonPath('data.sequence_preview.0.source_type', 'telegram')
        ->assertJsonPath('data.sequence_preview.0.caption', 'Entrada principal no palco')
        ->assertJsonPath('data.sequence_preview.0.layout_hint', 'cinematic')
        ->assertJsonPath('data.sequence_preview.0.preview_url', rtrim((string) config('app.url'), '/')."/storage/events/{$event->id}/variants/{$carlaPublished->id}/thumb.webp")
        ->assertJsonPath('data.sequence_preview.1.sender_name', 'Bruno')
        ->assertJsonPath('data.sequence_preview.2.sender_name', 'Ana');
});
