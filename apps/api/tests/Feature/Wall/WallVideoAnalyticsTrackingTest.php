<?php

use App\Modules\Analytics\Models\AnalyticsEvent;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\Wall\Models\EventWallSetting;

function enableTrackedWallModule(Event $event): void
{
    EventModule::query()->create([
        'event_id' => $event->id,
        'module_key' => 'wall',
        'is_enabled' => true,
    ]);
}

function wallHeartbeatPayload(array $overrides = []): array
{
    return array_merge([
        'player_instance_id' => 'player-analytics',
        'runtime_status' => 'playing',
        'connection_status' => 'connected',
        'ready_count' => 3,
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
        'hardware_concurrency' => 8,
        'device_memory_gb' => 16,
        'network_effective_type' => '4g',
        'network_save_data' => false,
        'network_downlink_mbps' => 24.5,
        'network_rtt_ms' => 68,
        'prefers_reduced_motion' => false,
        'document_visibility_state' => 'visible',
        'last_sync_at' => now()->toIso8601String(),
    ], $overrides);
}

it('tracks video lifecycle transitions from heartbeat updates into analytics events', function () {
    $event = Event::factory()->active()->create();
    enableTrackedWallModule($event);

    $settings = EventWallSetting::factory()->live()->create([
        'event_id' => $event->id,
    ]);

    $this->postJson("/api/v1/public/wall/{$settings->wall_code}/heartbeat", wallHeartbeatPayload([
        'current_item_id' => 'media_55',
        'current_media_type' => 'video',
        'current_video_phase' => 'starting',
        'current_video_duration_seconds' => 20,
        'current_video_ready_state' => 1,
    ]))->assertOk();

    $this->postJson("/api/v1/public/wall/{$settings->wall_code}/heartbeat", wallHeartbeatPayload([
        'current_item_id' => 'media_55',
        'current_media_type' => 'video',
        'current_video_phase' => 'playing',
        'current_video_duration_seconds' => 20,
        'current_video_position_seconds' => 1,
        'current_video_ready_state' => 3,
        'current_video_first_frame_ready' => true,
        'current_video_playback_ready' => true,
        'current_video_playing_confirmed' => true,
    ]))->assertOk();

    $this->postJson("/api/v1/public/wall/{$settings->wall_code}/heartbeat", wallHeartbeatPayload([
        'current_item_id' => 'media_55',
        'current_media_type' => 'video',
        'current_video_phase' => 'waiting',
        'current_video_duration_seconds' => 20,
        'current_video_position_seconds' => 5,
        'current_video_ready_state' => 2,
        'current_video_first_frame_ready' => true,
        'current_video_playback_ready' => false,
    ]))->assertOk();

    $this->postJson("/api/v1/public/wall/{$settings->wall_code}/heartbeat", wallHeartbeatPayload([
        'current_item_id' => 'media_55',
        'current_media_type' => 'video',
        'current_video_phase' => 'stalled',
        'current_video_duration_seconds' => 20,
        'current_video_position_seconds' => 6,
        'current_video_ready_state' => 2,
        'current_video_stall_count' => 1,
        'current_video_first_frame_ready' => true,
    ]))->assertOk();

    $this->postJson("/api/v1/public/wall/{$settings->wall_code}/heartbeat", wallHeartbeatPayload([
        'current_item_id' => 'media_99',
        'current_media_type' => 'image',
        'current_video_exit_reason' => 'cap_reached',
    ]))->assertOk();

    expect(AnalyticsEvent::query()->where('event_id', $event->id)->where('event_name', 'wall.video_start')->count())->toBe(1)
        ->and(AnalyticsEvent::query()->where('event_id', $event->id)->where('event_name', 'wall.video_first_frame')->count())->toBe(1)
        ->and(AnalyticsEvent::query()->where('event_id', $event->id)->where('event_name', 'wall.video_waiting')->count())->toBe(1)
        ->and(AnalyticsEvent::query()->where('event_id', $event->id)->where('event_name', 'wall.video_stalled')->count())->toBe(1)
        ->and(AnalyticsEvent::query()->where('event_id', $event->id)->where('event_name', 'wall.video_interrupted_by_cap')->count())->toBe(1);

    $capEvent = AnalyticsEvent::query()
        ->where('event_id', $event->id)
        ->where('event_name', 'wall.video_interrupted_by_cap')
        ->first();

    expect($capEvent?->event_media_id)->toBe(55)
        ->and($capEvent?->metadata_json['network_effective_type'] ?? null)->toBe('4g')
        ->and($capEvent?->metadata_json['hardware_concurrency'] ?? null)->toBe(8);
});

it('deduplicates repeated play rejection heartbeats and records the failure cause once', function () {
    $event = Event::factory()->active()->create();
    enableTrackedWallModule($event);

    $settings = EventWallSetting::factory()->live()->create([
        'event_id' => $event->id,
    ]);

    $this->postJson("/api/v1/public/wall/{$settings->wall_code}/heartbeat", wallHeartbeatPayload([
        'current_item_id' => 'media_88',
        'current_media_type' => 'video',
        'current_video_phase' => 'starting',
        'current_video_duration_seconds' => 12,
        'current_video_ready_state' => 1,
    ]))->assertOk();

    $rejectedPayload = wallHeartbeatPayload([
        'current_item_id' => 'media_88',
        'current_media_type' => 'video',
        'current_video_phase' => 'failed_to_start',
        'current_video_exit_reason' => 'startup_play_rejected',
        'current_video_failure_reason' => 'autoplay_blocked',
        'current_video_duration_seconds' => 12,
        'current_video_ready_state' => 1,
    ]);

    $this->postJson("/api/v1/public/wall/{$settings->wall_code}/heartbeat", $rejectedPayload)->assertOk();
    $this->postJson("/api/v1/public/wall/{$settings->wall_code}/heartbeat", $rejectedPayload)->assertOk();

    $rejectionEvent = AnalyticsEvent::query()
        ->where('event_id', $event->id)
        ->where('event_name', 'wall.video_play_rejected')
        ->first();

    expect(AnalyticsEvent::query()->where('event_id', $event->id)->where('event_name', 'wall.video_play_rejected')->count())->toBe(1)
        ->and($rejectionEvent?->event_media_id)->toBe(88)
        ->and($rejectionEvent?->metadata_json['current_video_failure_reason'] ?? null)->toBe('autoplay_blocked');
});
