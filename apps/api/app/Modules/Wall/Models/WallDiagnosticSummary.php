<?php

namespace App\Modules\Wall\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WallDiagnosticSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_wall_setting_id',
        'health_status',
        'total_players',
        'online_players',
        'offline_players',
        'degraded_players',
        'ready_count',
        'loading_count',
        'error_count',
        'stale_count',
        'cache_enabled_players',
        'persistent_storage_players',
        'cache_hit_rate_avg',
        'cache_usage_bytes_max',
        'cache_quota_bytes_max',
        'cache_stale_fallback_count',
        'last_seen_at',
        'refreshed_at',
    ];

    protected $casts = [
        'total_players' => 'integer',
        'online_players' => 'integer',
        'offline_players' => 'integer',
        'degraded_players' => 'integer',
        'ready_count' => 'integer',
        'loading_count' => 'integer',
        'error_count' => 'integer',
        'stale_count' => 'integer',
        'cache_enabled_players' => 'integer',
        'persistent_storage_players' => 'integer',
        'cache_hit_rate_avg' => 'integer',
        'cache_usage_bytes_max' => 'integer',
        'cache_quota_bytes_max' => 'integer',
        'cache_stale_fallback_count' => 'integer',
        'last_seen_at' => 'datetime',
        'refreshed_at' => 'datetime',
    ];

    public function wallSetting(): BelongsTo
    {
        return $this->belongsTo(EventWallSetting::class, 'event_wall_setting_id');
    }
}
