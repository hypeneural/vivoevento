<?php

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Enums\InvoiceStatus;
use App\Shared\Concerns\HasOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Invoice extends Model
{
    use HasFactory, HasOrganization;

    protected static function newFactory(): \Database\Factories\InvoiceFactory
    {
        return \Database\Factories\InvoiceFactory::new();
    }

    protected $fillable = [
        'organization_id',
        'billing_order_id',
        'subscription_id',
        'subscription_cycle_id',
        'gateway_invoice_id',
        'gateway_charge_id',
        'gateway_cycle_id',
        'invoice_number',
        'status',
        'gateway_status',
        'amount_cents',
        'currency',
        'issued_at',
        'due_at',
        'paid_at',
        'period_start_at',
        'period_end_at',
        'snapshot_json',
        'raw_gateway_json',
    ];

    protected $casts = [
        'status' => InvoiceStatus::class,
        'amount_cents' => 'integer',
        'issued_at' => 'datetime',
        'due_at' => 'datetime',
        'paid_at' => 'datetime',
        'period_start_at' => 'datetime',
        'period_end_at' => 'datetime',
        'snapshot_json' => 'array',
        'raw_gateway_json' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(BillingOrder::class, 'billing_order_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function subscriptionCycle(): BelongsTo
    {
        return $this->belongsTo(SubscriptionCycle::class, 'subscription_cycle_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }
}
