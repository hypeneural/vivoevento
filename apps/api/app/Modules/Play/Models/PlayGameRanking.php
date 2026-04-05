<?php

namespace App\Modules\Play\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayGameRanking extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_game_id',
        'player_identifier',
        'player_name',
        'best_score',
        'best_time_ms',
        'best_moves',
        'total_sessions',
        'total_wins',
        'last_played_at',
        'metrics_json',
    ];

    protected $casts = [
        'best_score' => 'integer',
        'best_time_ms' => 'integer',
        'best_moves' => 'integer',
        'total_sessions' => 'integer',
        'total_wins' => 'integer',
        'last_played_at' => 'datetime',
        'metrics_json' => 'array',
    ];

    public function eventGame(): BelongsTo
    {
        return $this->belongsTo(PlayEventGame::class, 'event_game_id');
    }
}
