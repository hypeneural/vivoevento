<?php

namespace App\Modules\Users\Models;

use App\Shared\Concerns\HasAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasRoles, HasAudit;

    protected static function newFactory(): \Database\Factories\UserFactory
    {
        return \Database\Factories\UserFactory::new();
    }

    protected $fillable = [
        'name',
        'email',
        'phone',
        'avatar_path',
        'password',
        'status',
        'last_login_at',
        'preferences',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'preferences' => 'array',
        ];
    }

    // ─── Relationships ─────────────────────────────────────

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Modules\Organizations\Models\Organization::class,
            'organization_members'
        )
            ->withPivot(['role_key', 'is_owner', 'invited_by', 'status', 'invited_at', 'joined_at'])
            ->withTimestamps();
    }

    public function organizationMembers(): HasMany
    {
        return $this->hasMany(\App\Modules\Organizations\Models\OrganizationMember::class);
    }

    public function eventTeamMembers(): HasMany
    {
        return $this->hasMany(\App\Modules\EventTeam\Models\EventTeamMember::class);
    }

    /**
     * Get the user's current/default organization.
     */
    public function currentOrganization(): ?\App\Modules\Organizations\Models\Organization
    {
        $activeContext = $this->preferences['active_context'] ?? null;

        if (is_array($activeContext) && ($activeContext['type'] ?? null) === 'event') {
            return null;
        }

        $preferredOrganizationId = is_array($activeContext) && ($activeContext['type'] ?? null) === 'organization'
            ? (int) ($activeContext['organization_id'] ?? 0)
            : 0;

        if ($preferredOrganizationId > 0) {
            if ($this->hasAnyRole(['super-admin', 'platform-admin'])) {
                return \App\Modules\Organizations\Models\Organization::query()->find($preferredOrganizationId);
            }

            return $this->organizations()->where('organizations.id', $preferredOrganizationId)->first();
        }

        return $this->organizations()->first();
    }
}
