<?php

namespace App\Modules\Wall\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WallHeartbeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'player_instance_id' => ['required', 'string', 'max:120'],
            'runtime_status' => ['required', Rule::in(['booting', 'idle', 'playing', 'paused', 'stopped', 'expired', 'error'])],
            'connection_status' => ['required', Rule::in(['idle', 'connecting', 'connected', 'reconnecting', 'disconnected', 'error'])],
            'current_item_id' => ['nullable', 'string', 'max:120'],
            'current_item_started_at' => ['nullable', 'date'],
            'active_transition_effect' => ['nullable', Rule::in(['fade', 'slide', 'zoom', 'flip', 'lift-fade', 'cross-zoom', 'swipe-up', 'none'])],
            'transition_mode' => ['nullable', Rule::in(['fixed', 'random'])],
            'transition_random_pick_count' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'transition_fallback_count' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'transition_last_fallback_reason' => ['nullable', Rule::in(['reduced_motion', 'capability_tier', 'effect_unavailable'])],
            'current_sender_key' => ['nullable', 'string', 'max:180'],
            'current_media_type' => ['nullable', Rule::in(['image', 'video'])],
            'current_video_phase' => ['nullable', Rule::in(['idle', 'probing', 'primed', 'starting', 'playing', 'waiting', 'stalled', 'paused_by_wall', 'completed', 'capped', 'interrupted', 'failed_to_start'])],
            'current_video_exit_reason' => ['nullable', Rule::in(['ended', 'cap_reached', 'paused_by_operator', 'play_rejected', 'stalled_timeout', 'replaced_by_command', 'media_deleted', 'visibility_degraded', 'startup_timeout', 'poster_then_skip', 'startup_waiting_timeout', 'startup_play_rejected'])],
            'current_video_failure_reason' => ['nullable', Rule::in(['network_error', 'unsupported_format', 'autoplay_blocked', 'decode_degraded', 'src_missing', 'variant_missing'])],
            'current_video_position_seconds' => ['nullable', 'numeric', 'min:0'],
            'current_video_duration_seconds' => ['nullable', 'numeric', 'min:0'],
            'current_video_ready_state' => ['nullable', 'integer', 'min:0', 'max:4'],
            'current_video_stall_count' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'current_video_poster_visible' => ['nullable', 'boolean'],
            'current_video_first_frame_ready' => ['nullable', 'boolean'],
            'current_video_playback_ready' => ['nullable', 'boolean'],
            'current_video_playing_confirmed' => ['nullable', 'boolean'],
            'current_video_startup_degraded' => ['nullable', 'boolean'],
            'hardware_concurrency' => ['nullable', 'integer', 'min:1', 'max:256'],
            'device_memory_gb' => ['nullable', 'numeric', 'min:0', 'max:1024'],
            'network_effective_type' => ['nullable', Rule::in(['slow-2g', '2g', '3g', '4g', 'unknown'])],
            'network_save_data' => ['nullable', 'boolean'],
            'network_downlink_mbps' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'network_rtt_ms' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'prefers_reduced_motion' => ['nullable', 'boolean'],
            'document_visibility_state' => ['nullable', Rule::in(['visible', 'hidden', 'prerender', 'unloaded'])],
            'ready_count' => ['required', 'integer', 'min:0', 'max:5000'],
            'loading_count' => ['required', 'integer', 'min:0', 'max:5000'],
            'error_count' => ['required', 'integer', 'min:0', 'max:5000'],
            'stale_count' => ['required', 'integer', 'min:0', 'max:5000'],
            'cache_enabled' => ['required', 'boolean'],
            'persistent_storage' => ['required', Rule::in(['none', 'localstorage', 'indexeddb', 'cache_api', 'unavailable', 'unknown'])],
            'cache_usage_bytes' => ['nullable', 'integer', 'min:0'],
            'cache_quota_bytes' => ['nullable', 'integer', 'min:0'],
            'cache_hit_count' => ['required', 'integer', 'min:0', 'max:1000000'],
            'cache_miss_count' => ['required', 'integer', 'min:0', 'max:1000000'],
            'cache_stale_fallback_count' => ['required', 'integer', 'min:0', 'max:1000000'],
            'board_piece_count' => ['nullable', 'integer', 'min:0', 'max:100'],
            'board_burst_count' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'board_budget_downgrade_count' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'decode_backlog_count' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'board_reset_count' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'board_budget_downgrade_reason' => ['nullable', Rule::in(['small_stage', 'safe_area_pressure', 'runtime_budget'])],
            'last_sync_at' => ['nullable', 'date'],
            'last_fallback_reason' => ['nullable', 'string', 'max:120'],
        ];
    }
}
