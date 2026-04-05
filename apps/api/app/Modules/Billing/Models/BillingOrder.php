<?php

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Enums\BillingOrderMode;
use App\Modules\Billing\Enums\BillingOrderStatus;
use App\Shared\Concerns\HasOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class BillingOrder extends Model
{
    use HasFactory, HasOrganization;

    protected static function newFactory(): \Database\Factories\BillingOrderFactory
    {
        return \Database\Factories\BillingOrderFactory::new();
    }

    protected $fillable = [
        'uuid',
        'organization_id',
        'event_id',
        'buyer_user_id',
        'mode',
        'status',
        'currency',
        'total_cents',
        'payment_method',
        'gateway_provider',
        'gateway_order_id',
        'idempotency_key',
        'gateway_charge_id',
        'gateway_transaction_id',
        'gateway_status',
        'confirmed_at',
        'expires_at',
        'paid_at',
        'failed_at',
        'canceled_at',
        'refunded_at',
        'customer_snapshot_json',
        'gateway_response_json',
        'metadata_json',
    ];

    protected $casts = [
        'mode' => BillingOrderMode::class,
        'status' => BillingOrderStatus::class,
        'total_cents' => 'integer',
        'confirmed_at' => 'datetime',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
        'canceled_at' => 'datetime',
        'refunded_at' => 'datetime',
        'customer_snapshot_json' => 'array',
        'gateway_response_json' => 'array',
        'metadata_json' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (BillingOrder $order) {
            if (empty($order->uuid)) {
                $order->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Events\Models\Event::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Users\Models\User::class, 'buyer_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillingOrderItem::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(EventPurchase::class, 'billing_order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'billing_order_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'billing_order_id');
    }

    public function gatewayEvents(): HasMany
    {
        return $this->hasMany(BillingGatewayEvent::class, 'billing_order_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(BillingOrderNotification::class, 'billing_order_id');
    }
}
