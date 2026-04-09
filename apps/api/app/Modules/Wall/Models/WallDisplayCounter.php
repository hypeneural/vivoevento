<?php

namespace App\Modules\Wall\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WallDisplayCounter extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_wall_setting_id',
        'displayed_count',
        'current_item_id',
        'current_item_started_at',
        'last_player_instance_id',
        'last_counted_at',
    ];

    protected $casts = [
        'displayed_count' => 'integer',
        'current_item_started_at' => 'datetime',
        'last_counted_at' => 'datetime',
    ];

    public function wallSetting(): BelongsTo
    {
        return $this->belongsTo(EventWallSetting::class, 'event_wall_setting_id');
    }
}
