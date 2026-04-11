<?php

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\Wall\Events\PrivateWallLiveSnapshotUpdated;
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

it('persists video runtime fields in diagnostics and marks waiting video playback as degraded', function () {
    [$user, $organization] = $this->actingAsManager();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);
    enableWallModule($event);

    $settings = EventWallSetting::factory()->live()->create([
        'event_id' => $event->id,
    ]);

    $this->postJson("/api/v1/public/wall/{$settings->wall_code}/heartbeat", [
        'player_instance_id' => 'player-video',
        'runtime_status' => 'playing',
        'connection_status' => 'connected',
        'current_item_id' => 'media_77',
        'current_sender_key' => 'whatsapp:5511888888888',
        'current_media_type' => 'video',
        'current_video_phase' => 'waiting',
        'current_video_exit_reason' => 'startup_waiting_timeout',
        'current_video_failure_reason' => 'network_error',
        'current_video_position_seconds' => 4.5,
        'current_video_duration_seconds' => 18,
        'current_video_ready_state' => 2,
        'current_video_stall_count' => 1,
        'current_video_poster_visible' => true,
        'current_video_first_frame_ready' => true,
        'current_video_playback_ready' => false,
        'current_video_playing_confirmed' => false,
        'current_video_startup_degraded' => true,
        'hardware_concurrency' => 8,
        'device_memory_gb' => 16,
        'network_effective_type' => '4g',
        'network_save_data' => false,
        'network_downlink_mbps' => 24.5,
        'network_rtt_ms' => 68,
        'prefers_reduced_motion' => false,
        'document_visibility_state' => 'visible',
        'ready_count' => 2,
        'loading_count' => 1,
        'error_count' => 0,
        'stale_count' => 0,
        'cache_enabled' => true,
        'persistent_storage' => 'indexeddb',
        'cache_usage_bytes' => 1048576,
        'cache_quota_bytes' => 8388608,
        'cache_hit_count' => 8,
        'cache_miss_count' => 2,
        'cache_stale_fallback_count' => 0,
        'last_sync_at' => now()->toIso8601String(),
        'last_fallback_reason' => null,
    ])->assertOk();

    $response = $this->apiGet("/events/{$event->id}/wall/diagnostics");

    $this->assertApiSuccess($response);

    $response->assertJsonPath('data.summary.health_status', 'degraded')
        ->assertJsonPath('data.players.0.health_status', 'degraded')
        ->assertJsonPath('data.players.0.current_media_type', 'video')
        ->assertJsonPath('data.players.0.current_video_phase', 'waiting')
        ->assertJsonPath('data.players.0.current_video_failure_reason', 'network_error')
        ->assertJsonPath('data.players.0.current_video_stall_count', 1)
        ->assertJsonPath('data.players.0.current_video_poster_visible', true)
        ->assertJsonPath('data.players.0.current_video_startup_degraded', true)
        ->assertJsonPath('data.players.0.hardware_concurrency', 8)
        ->assertJsonPath('data.players.0.device_memory_gb', 16)
        ->assertJsonPath('data.players.0.network_effective_type', '4g')
        ->assertJsonPath('data.players.0.network_save_data', false)
        ->assertJsonPath('data.players.0.network_downlink_mbps', 24.5)
        ->assertJsonPath('data.players.0.network_rtt_ms', 68)
        ->assertJsonPath('data.players.0.prefers_reduced_motion', false)
        ->assertJsonPath('data.players.0.document_visibility_state', 'visible');
});

it('persists board runtime counters and downgrade reason in diagnostics payloads', function () {
    [$user, $organization] = $this->actingAsManager();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);
    enableWallModule($event);

    $settings = EventWallSetting::factory()->live()->create([
        'event_id' => $event->id,
    ]);

    $this->postJson("/api/v1/public/wall/{$settings->wall_code}/heartbeat", [
        'player_instance_id' => 'player-puzzle-runtime',
        'runtime_status' => 'playing',
        'connection_status' => 'connected',
        'current_item_id' => 'media_99',
        'current_sender_key' => 'whatsapp:5511888888888',
        'ready_count' => 6,
        'loading_count' => 1,
        'error_count' => 0,
        'stale_count' => 0,
        'cache_enabled' => true,
        'persistent_storage' => 'indexeddb',
        'cache_usage_bytes' => 1048576,
        'cache_quota_bytes' => 8388608,
        'cache_hit_count' => 18,
        'cache_miss_count' => 2,
        'cache_stale_fallback_count' => 0,
        'board_piece_count' => 6,
        'board_burst_count' => 8,
        'board_budget_downgrade_count' => 2,
        'decode_backlog_count' => 1,
        'board_reset_count' => 3,
        'board_budget_downgrade_reason' => 'small_stage',
        'last_sync_at' => now()->toIso8601String(),
        'last_fallback_reason' => null,
    ])->assertOk();

    $response = $this->apiGet("/events/{$event->id}/wall/diagnostics");

    $this->assertApiSuccess($response);

    $response->assertJsonPath('data.players.0.board_piece_count', 6)
        ->assertJsonPath('data.players.0.board_burst_count', 8)
        ->assertJsonPath('data.players.0.board_budget_downgrade_count', 2)
        ->assertJsonPath('data.players.0.decode_backlog_count', 1)
        ->assertJsonPath('data.players.0.board_reset_count', 3)
        ->assertJsonPath('data.players.0.board_budget_downgrade_reason', 'small_stage');

    $runtimeStatus = WallPlayerRuntimeStatus::query()->firstWhere('player_instance_id', 'player-puzzle-runtime');

    expect($runtimeStatus)->not->toBeNull()
        ->and($runtimeStatus?->board_piece_count)->toBe(6)
        ->and($runtimeStatus?->board_budget_downgrade_reason)->toBe('small_stage');
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

it('broadcasts a dedicated live snapshot update on heartbeat for the manager channel', function () {
    $event = Event::factory()->active()->create();
    enableWallModule($event);

    $settings = EventWallSetting::factory()->live()->create([
        'event_id' => $event->id,
    ]);

    EventFacade::fake([PrivateWallLiveSnapshotUpdated::class]);

    $this->postJson("/api/v1/public/wall/{$settings->wall_code}/heartbeat", [
        'player_instance_id' => 'player-snapshot-broadcast',
        'runtime_status' => 'playing',
        'connection_status' => 'connected',
        'current_item_id' => 'media_88',
        'current_sender_key' => 'whatsapp:5511666666666',
        'ready_count' => 4,
        'loading_count' => 0,
        'error_count' => 0,
        'stale_count' => 0,
        'cache_enabled' => true,
        'persistent_storage' => 'indexeddb',
        'cache_usage_bytes' => 1048576,
        'cache_quota_bytes' => 8388608,
        'cache_hit_count' => 9,
        'cache_miss_count' => 1,
        'cache_stale_fallback_count' => 0,
        'last_sync_at' => now()->toIso8601String(),
        'last_fallback_reason' => null,
    ])->assertOk();

    EventFacade::assertDispatched(
        PrivateWallLiveSnapshotUpdated::class,
        fn (PrivateWallLiveSnapshotUpdated $eventPayload) => $eventPayload->eventId === $event->id
            && ($eventPayload->payload['currentPlayer']['playerInstanceId'] ?? null) === 'player-snapshot-broadcast'
            && ($eventPayload->payload['advancedAt'] ?? null) !== null,
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

    $brunoPublished = \App\Modules\MediaProcessing\Models\EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $messageB->id,
        'media_type' => 'video',
        'mime_type' => 'video/mp4',
        'source_type' => 'public_upload',
        'duration_seconds' => 18,
        'video_codec' => 'h264',
        'container' => 'mp4',
        'published_at' => now()->subMinute(),
    ]);

    $brunoPublished->variants()->create([
        'variant_key' => 'wall_video_poster',
        'disk' => 'public',
        'path' => "events/{$event->id}/variants/{$brunoPublished->id}/wall_video_poster.webp",
        'mime_type' => 'image/webp',
        'width' => 720,
        'height' => 1280,
        'size_bytes' => 28145,
    ]);

    $brunoPublished->variants()->create([
        'variant_key' => 'wall_video_720p',
        'disk' => 'public',
        'path' => "events/{$event->id}/variants/{$brunoPublished->id}/wall_video_720p.mp4",
        'mime_type' => 'video/mp4',
        'width' => 720,
        'height' => 1280,
        'size_bytes' => 1420000,
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
        ->assertJsonPath('data.sequence_preview.1.source_type', 'upload')
        ->assertJsonPath('data.sequence_preview.1.is_video', true)
        ->assertJsonPath('data.sequence_preview.1.duration_seconds', 18)
        ->assertJsonPath('data.sequence_preview.1.video_policy_label', 'Video com duracao diferenciada')
        ->assertJsonPath('data.sequence_preview.1.video_admission.state', 'eligible')
        ->assertJsonPath('data.sequence_preview.1.served_variant_key', 'wall_video_720p')
        ->assertJsonPath('data.sequence_preview.1.preview_variant_key', 'wall_video_poster')
        ->assertJsonPath('data.sequence_preview.2.sender_name', 'Ana');
});

it('simulation excludes media that are not eligible for the current wall orientation', function () {
    [$user, $organization] = $this->actingAsManager();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);
    enableWallModule($event);

    EventWallSetting::factory()->live()->create([
        'event_id' => $event->id,
        'accepted_orientation' => 'landscape',
    ]);

    $landscape = \App\Modules\MediaProcessing\Models\EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'source_type' => 'whatsapp',
        'source_label' => 'Ana',
        'width' => 1920,
        'height' => 1080,
        'published_at' => now(),
    ]);

    $portrait = \App\Modules\MediaProcessing\Models\EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'media_type' => 'video',
        'mime_type' => 'video/mp4',
        'source_type' => 'whatsapp',
        'source_label' => 'Bia',
        'width' => 1080,
        'height' => 1920,
        'published_at' => now()->subSecond(),
    ]);

    $response = $this->apiPost("/events/{$event->id}/wall/simulate", [
        'selection_mode' => 'balanced',
        'event_phase' => 'flow',
        'interval_ms' => 8000,
    ]);

    $this->assertApiSuccess($response);

    $previewIds = collect($response->json('data.sequence_preview'))->pluck('item_id')->all();

    expect($previewIds)->toContain('media_'.$landscape->id)
        ->and($previewIds)->not->toContain('media_'.$portrait->id);
});

it('simulation excludes original-only videos when strict wall video gate is enabled', function () {
    [$user, $organization] = $this->actingAsManager();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);
    enableWallModule($event);

    EventWallSetting::factory()->live()->create([
        'event_id' => $event->id,
    ]);

    $fallbackVideo = \App\Modules\MediaProcessing\Models\EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'media_type' => 'video',
        'mime_type' => 'video/mp4',
        'source_type' => 'public_upload',
        'source_label' => 'Bia',
        'width' => 1920,
        'height' => 1080,
        'duration_seconds' => 18,
        'video_codec' => 'h264',
        'container' => 'mp4',
        'published_at' => now(),
    ]);

    $preparedVideo = \App\Modules\MediaProcessing\Models\EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'media_type' => 'video',
        'mime_type' => 'video/mp4',
        'source_type' => 'public_upload',
        'source_label' => 'Carla',
        'width' => 1280,
        'height' => 720,
        'duration_seconds' => 12,
        'video_codec' => 'h264',
        'container' => 'mp4',
        'published_at' => now()->subSecond(),
    ]);

    $preparedVideo->variants()->create([
        'variant_key' => 'wall_video_720p',
        'disk' => 'public',
        'path' => "events/{$event->id}/variants/{$preparedVideo->id}/wall_video_720p.mp4",
        'mime_type' => 'video/mp4',
        'width' => 1280,
        'height' => 720,
        'size_bytes' => 1420000,
    ]);

    $preparedVideo->variants()->create([
        'variant_key' => 'wall_video_poster',
        'disk' => 'public',
        'path' => "events/{$event->id}/variants/{$preparedVideo->id}/wall_video_poster.jpg",
        'mime_type' => 'image/jpeg',
        'width' => 1280,
        'height' => 720,
        'size_bytes' => 32145,
    ]);

    $response = $this->apiPost("/events/{$event->id}/wall/simulate", [
        'selection_mode' => 'balanced',
        'event_phase' => 'flow',
        'interval_ms' => 8000,
    ]);

    $this->assertApiSuccess($response);

    $previewIds = collect($response->json('data.sequence_preview'))->pluck('item_id')->all();

    expect($previewIds)->toContain('media_'.$preparedVideo->id)
        ->and($previewIds)->not->toContain('media_'.$fallbackVideo->id);
});
