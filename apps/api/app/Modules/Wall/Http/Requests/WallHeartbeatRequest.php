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
            'last_sync_at' => ['nullable', 'date'],
            'last_fallback_reason' => ['nullable', 'string', 'max:120'],
        ];
    }
}
