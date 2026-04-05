<?php

namespace App\Modules\Play\Models;

use App\Modules\Play\Enums\PlayGameSessionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PlayGameSession extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\PlayGameSessionFactory
    {
        return \Database\Factories\PlayGameSessionFactory::new();
    }

    protected $fillable = [
        'uuid',
        'event_game_id',
        'player_identifier',
        'player_name',
        'resume_token',
        'status',
        'started_at',
        'last_activity_at',
        'expires_at',
        'finished_at',
        'result_json',
    ];

    protected $casts = [
        'status' => PlayGameSessionStatus::class,
        'started_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'expires_at' => 'datetime',
        'finished_at' => 'datetime',
        'result_json' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $session): void {
            if (! $session->uuid) {
                $session->uuid = (string) Str::uuid();
            }
        });
    }

    public function eventGame(): BelongsTo
    {
        return $this->belongsTo(PlayEventGame::class, 'event_game_id');
    }

    public function moves(): HasMany
    {
        return $this->hasMany(PlayGameMove::class, 'game_session_id');
    }
}
