<?php

namespace App\Modules\Organizations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationMember extends Model
{
    protected $fillable = [
        'organization_id',
        'user_id',
        'role_key',
        'is_owner',
        'invited_by',
        'status',
        'invited_at',
        'joined_at',
    ];

    protected $casts = [
        'is_owner' => 'boolean',
        'invited_at' => 'datetime',
        'joined_at' => 'datetime',
    ];

    // ─── Relationships ─────────────────────────────────────

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Users\Models\User::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Users\Models\User::class, 'invited_by');
    }

    // ─── Scopes ───────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
