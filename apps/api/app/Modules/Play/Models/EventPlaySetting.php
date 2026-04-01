<?php
namespace App\Modules\Play\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventPlaySetting extends Model
{
    protected $fillable = [
        'event_id', 'is_enabled', 'memory_enabled', 'puzzle_enabled',
        'memory_card_count', 'puzzle_piece_count', 'auto_refresh_assets',
        'ranking_enabled',
    ];

    protected $casts = [
        'is_enabled' => 'boolean', 'memory_enabled' => 'boolean',
        'puzzle_enabled' => 'boolean', 'auto_refresh_assets' => 'boolean',
        'ranking_enabled' => 'boolean', 'memory_card_count' => 'integer',
        'puzzle_piece_count' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Events\Models\Event::class);
    }
}
