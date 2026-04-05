<?php

namespace App\Modules\Play\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayGameMove extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'game_session_id',
        'move_number',
        'move_type',
        'payload_json',
        'occurred_at',
        'created_at',
    ];

    protected $casts = [
        'move_number' => 'integer',
        'payload_json' => 'array',
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(PlayGameSession::class, 'game_session_id');
    }
}
