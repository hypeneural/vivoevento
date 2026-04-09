<?php
namespace App\Modules\Billing\Models;

use App\Shared\Concerns\HasOrganization;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Subscription extends Model
{
    use HasFactory, HasOrganization;

    protected $fillable = [
        'organization_id', 'plan_id', 'plan_price_id', 'status', 'billing_cycle',
        'payment_method', 'starts_at', 'trial_ends_at', 'current_period_started_at',
        'current_period_ends_at', 'renews_at', 'next_billing_at', 'ends_at',
        'canceled_at', 'cancel_at_period_end', 'cancel_requested_at',
        'gateway_provider', 'gateway_customer_id', 'gateway_plan_id',
        'gateway_card_id', 'gateway_status_reason', 'billing_type',
        'contract_status', 'billing_status', 'access_status',
        'gateway_subscription_id', 'metadata_json',
    ];

    protected $casts = [
        'starts_at' => 'datetime', 'trial_ends_at' => 'datetime',
        'current_period_started_at' => 'datetime',
        'current_period_ends_at' => 'datetime',
        'renews_at' => 'datetime',
        'next_billing_at' => 'datetime',
        'ends_at' => 'datetime',
        'canceled_at' => 'datetime',
        'cancel_requested_at' => 'datetime',
        'cancel_at_period_end' => 'boolean',
        'metadata_json' => 'array',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Plans\Models\Plan::class);
    }

    public function planPrice(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Plans\Models\PlanPrice::class);
    }

    public function cycles(): HasMany
    {
        return $this->hasMany(SubscriptionCycle::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isActiveForEntitlements(?Carbon $reference = null): bool
    {
        $reference ??= now();

        if (in_array($this->status, ['trialing', 'active'], true)) {
            return true;
        }

        return $this->status === 'canceled'
            && $this->ends_at instanceof Carbon
            && $this->ends_at->greaterThan($reference);
    }

    public function isCanceledPendingEnd(?Carbon $reference = null): bool
    {
        $reference ??= now();

        return $this->status === 'canceled'
            && $this->ends_at instanceof Carbon
            && $this->ends_at->greaterThan($reference);
    }
}
