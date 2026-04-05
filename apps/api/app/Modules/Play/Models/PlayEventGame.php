<?php

namespace App\Modules\Play\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PlayEventGame extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\PlayEventGameFactory
    {
        return \Database\Factories\PlayEventGameFactory::new();
    }

    protected $fillable = [
        'uuid',
        'event_id',
        'game_type_id',
        'title',
        'slug',
        'is_active',
        'sort_order',
        'ranking_enabled',
        'settings_json',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'ranking_enabled' => 'boolean',
        'settings_json' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $game): void {
            if (! $game->uuid) {
                $game->uuid = (string) Str::uuid();
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Events\Models\Event::class);
    }

    public function gameType(): BelongsTo
    {
        return $this->belongsTo(PlayGameType::class, 'game_type_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(PlayGameAsset::class, 'event_game_id')->orderBy('sort_order');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(PlayGameSession::class, 'event_game_id');
    }

    public function rankings(): HasMany
    {
        return $this->hasMany(PlayGameRanking::class, 'event_game_id');
    }
}
