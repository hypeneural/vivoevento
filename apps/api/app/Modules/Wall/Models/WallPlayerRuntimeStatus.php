<?php

namespace App\Modules\Wall\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WallPlayerRuntimeStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_wall_setting_id',
        'player_instance_id',
        'runtime_status',
        'connection_status',
        'current_item_id',
        'current_item_started_at',
        'current_sender_key',
        'current_media_type',
        'current_video_phase',
        'current_video_exit_reason',
        'current_video_failure_reason',
        'current_video_position_seconds',
        'current_video_duration_seconds',
        'current_video_ready_state',
        'current_video_stall_count',
        'current_video_poster_visible',
        'current_video_first_frame_ready',
        'current_video_playback_ready',
        'current_video_playing_confirmed',
        'current_video_startup_degraded',
        'ready_count',
        'loading_count',
        'error_count',
        'stale_count',
        'cache_enabled',
        'persistent_storage',
        'cache_usage_bytes',
        'cache_quota_bytes',
        'cache_hit_count',
        'cache_miss_count',
        'cache_stale_fallback_count',
        'last_sync_at',
        'last_heartbeat_at',
        'last_fallback_reason',
    ];

    protected $casts = [
        'ready_count' => 'integer',
        'loading_count' => 'integer',
        'error_count' => 'integer',
        'stale_count' => 'integer',
        'cache_enabled' => 'boolean',
        'current_video_position_seconds' => 'float',
        'current_video_duration_seconds' => 'float',
        'current_video_ready_state' => 'integer',
        'current_video_stall_count' => 'integer',
        'current_video_poster_visible' => 'boolean',
        'current_video_first_frame_ready' => 'boolean',
        'current_video_playback_ready' => 'boolean',
        'current_video_playing_confirmed' => 'boolean',
        'current_video_startup_degraded' => 'boolean',
        'cache_usage_bytes' => 'integer',
        'cache_quota_bytes' => 'integer',
        'cache_hit_count' => 'integer',
        'cache_miss_count' => 'integer',
        'cache_stale_fallback_count' => 'integer',
        'current_item_started_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'last_heartbeat_at' => 'datetime',
    ];

    public function wallSetting(): BelongsTo
    {
        return $this->belongsTo(EventWallSetting::class, 'event_wall_setting_id');
    }
}
