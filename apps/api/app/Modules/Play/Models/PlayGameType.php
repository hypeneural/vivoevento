<?php

namespace App\Modules\Play\Models;

use App\Modules\Play\Enums\PlayGameTypeKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlayGameType extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\PlayGameTypeFactory
    {
        return \Database\Factories\PlayGameTypeFactory::new();
    }

    protected $fillable = [
        'key',
        'name',
        'description',
        'enabled',
        'supports_ranking',
        'supports_photo_assets',
        'config_schema_json',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'supports_ranking' => 'boolean',
        'supports_photo_assets' => 'boolean',
        'config_schema_json' => 'array',
        'key' => PlayGameTypeKey::class,
    ];

    public function eventGames(): HasMany
    {
        return $this->hasMany(PlayEventGame::class, 'game_type_id');
    }
}
