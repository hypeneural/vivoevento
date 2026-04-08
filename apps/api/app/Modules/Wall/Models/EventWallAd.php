<?php

namespace App\Modules\Wall\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventWallAd extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\EventWallAdFactory
    {
        return \Database\Factories\EventWallAdFactory::new();
    }

    protected $fillable = [
        'event_wall_setting_id',
        'file_path',
        'media_type',
        'duration_seconds',
        'position',
        'is_active',
    ];

    protected $casts = [
        'duration_seconds' => 'integer',
        'position' => 'integer',
        'is_active' => 'boolean',
    ];

    public function wallSetting(): BelongsTo
    {
        return $this->belongsTo(EventWallSetting::class, 'event_wall_setting_id');
    }

    /**
     * Scope: only active ads, ordered by position.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('position');
    }
}
