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
        'current_sender_key',
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
        'cache_usage_bytes' => 'integer',
        'cache_quota_bytes' => 'integer',
        'cache_hit_count' => 'integer',
        'cache_miss_count' => 'integer',
        'cache_stale_fallback_count' => 'integer',
        'last_sync_at' => 'datetime',
        'last_heartbeat_at' => 'datetime',
    ];

    public function wallSetting(): BelongsTo
    {
        return $this->belongsTo(EventWallSetting::class, 'event_wall_setting_id');
    }
}
