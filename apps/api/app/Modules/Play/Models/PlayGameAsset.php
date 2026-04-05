<?php

namespace App\Modules\Play\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayGameAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_game_id',
        'media_id',
        'role',
        'sort_order',
        'metadata_json',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'metadata_json' => 'array',
    ];

    public function eventGame(): BelongsTo
    {
        return $this->belongsTo(PlayEventGame::class, 'event_game_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\MediaProcessing\Models\EventMedia::class, 'media_id');
    }
}
