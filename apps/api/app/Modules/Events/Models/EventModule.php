<?php

namespace App\Modules\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EventModule extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'module_key',
        'is_enabled',
        'settings_json',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'settings_json' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
