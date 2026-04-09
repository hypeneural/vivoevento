<?php

namespace App\Modules\Organizations\Models;

use App\Modules\Organizations\Enums\OrganizationType;
use App\Modules\Partners\Models\PartnerProfile;
use App\Modules\Partners\Models\PartnerStat;
use App\Shared\Concerns\HasAudit;
use App\Shared\Enums\Status;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Organization extends Model
{
    use HasFactory, SoftDeletes, HasAudit;

    protected $appends = ['name'];

    protected static function newFactory(): \Database\Factories\OrganizationFactory
    {
        return \Database\Factories\OrganizationFactory::new();
    }

    protected $fillable = [
        'uuid',
        'type',
        'name',
        'legal_name',
        'trade_name',
        'document_number',
        'slug',
        'email',
        'billing_email',
        'phone',
        'logo_path',
        'primary_color',
        'secondary_color',
        'subdomain',
        'custom_domain',
        'timezone',
        'status',
    ];

    protected $casts = [
        'type' => OrganizationType::class,
        'status' => Status::class,
    ];

    // ─── Boot ─────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Organization $org) {
            if (empty($org->uuid)) {
                $org->uuid = (string) Str::uuid();
            }
        });
    }

    // ─── Relationships ─────────────────────────────────────

    public function members(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function users(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(\App\Modules\Users\Models\User::class, 'organization_members')
            ->withPivot(['role_key', 'is_owner', 'invited_by', 'status', 'invited_at', 'joined_at'])
            ->withTimestamps();
    }

    public function clients(): HasMany
    {
        return $this->hasMany(\App\Modules\Clients\Models\Client::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(\App\Modules\Events\Models\Event::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(\App\Modules\Billing\Models\Subscription::class)->latest();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(\App\Modules\Billing\Models\Subscription::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(\App\Modules\Billing\Models\EventPurchase::class);
    }

    public function eventAccessGrants(): HasMany
    {
        return $this->hasMany(\App\Modules\Billing\Models\EventAccessGrant::class);
    }

    public function billingOrders(): HasMany
    {
        return $this->hasMany(\App\Modules\Billing\Models\BillingOrder::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(\App\Modules\Billing\Models\Invoice::class);
    }

    public function billingProfile(): HasOne
    {
        return $this->hasOne(\App\Modules\Billing\Models\BillingProfile::class);
    }

    public function partnerProfile(): HasOne
    {
        return $this->hasOne(PartnerProfile::class);
    }

    public function partnerStats(): HasOne
    {
        return $this->hasOne(PartnerStat::class);
    }

    // ─── Helpers ──────────────────────────────────────────

    public function displayName(): string
    {
        return $this->trade_name ?: $this->legal_name ?: $this->slug;
    }

    public function getNameAttribute(): string
    {
        return $this->displayName();
    }

    public function setNameAttribute(?string $value): void
    {
        if ($value !== null && $value !== '') {
            $this->attributes['trade_name'] = $value;
        }
    }
}
