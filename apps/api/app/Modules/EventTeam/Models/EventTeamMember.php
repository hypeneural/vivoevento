<?php

namespace App\Modules\EventTeam\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventTeamMember extends Model
{
    protected $fillable = [
        'event_id',
        'user_id',
        'role',
    ];

    // ─── Relationships ─────────────────────────────────────

    public function event(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Events\Models\Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Users\Models\User::class);
    }

    // ─── Scopes ───────────────────────────────────────────

    public function scopeManagers($query)
    {
        return $query->where('role', 'manager');
    }

    public function scopeOperators($query)
    {
        return $query->where('role', 'operator');
    }

    public function scopeModerators($query)
    {
        return $query->where('role', 'moderator');
    }
}
