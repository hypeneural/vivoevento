<?php

namespace App\Modules\Events\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventMediaSenderBlacklist extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\EventMediaSenderBlacklistFactory
    {
        return \Database\Factories\EventMediaSenderBlacklistFactory::new();
    }

    protected $fillable = [
        'event_id',
        'identity_type',
        'identity_value',
        'normalized_phone',
        'reason',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function isCurrentlyActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
