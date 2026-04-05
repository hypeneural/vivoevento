<?php
namespace App\Modules\Billing\Models;
use App\Shared\Concerns\HasOrganization;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Subscription extends Model
{
    use HasFactory, HasOrganization;

    protected $fillable = [
        'organization_id', 'plan_id', 'status', 'billing_cycle',
        'starts_at', 'trial_ends_at', 'renews_at', 'ends_at', 'canceled_at',
        'gateway_provider', 'gateway_customer_id', 'gateway_subscription_id',
    ];

    protected $casts = [
        'starts_at' => 'datetime', 'trial_ends_at' => 'datetime',
        'renews_at' => 'datetime', 'ends_at' => 'datetime', 'canceled_at' => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Plans\Models\Plan::class);
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
