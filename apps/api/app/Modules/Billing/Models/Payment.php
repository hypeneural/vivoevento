<?php

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\PaymentFactory
    {
        return \Database\Factories\PaymentFactory::new();
    }

    protected $fillable = [
        'billing_order_id',
        'subscription_id',
        'invoice_id',
        'status',
        'amount_cents',
        'currency',
        'payment_method',
        'gateway_provider',
        'gateway_payment_id',
        'gateway_order_id',
        'gateway_charge_id',
        'gateway_invoice_id',
        'gateway_transaction_id',
        'gateway_status',
        'gateway_charge_status',
        'paid_at',
        'expires_at',
        'failed_at',
        'canceled_at',
        'refunded_at',
        'card_brand',
        'card_last_four',
        'attempt_sequence',
        'last_transaction_json',
        'gateway_response_json',
        'acquirer_return_code',
        'acquirer_message',
        'qr_code',
        'qr_code_url',
        'raw_payload_json',
    ];

    protected $casts = [
        'status' => PaymentStatus::class,
        'amount_cents' => 'integer',
        'paid_at' => 'datetime',
        'expires_at' => 'datetime',
        'failed_at' => 'datetime',
        'canceled_at' => 'datetime',
        'refunded_at' => 'datetime',
        'attempt_sequence' => 'integer',
        'last_transaction_json' => 'array',
        'gateway_response_json' => 'array',
        'raw_payload_json' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(BillingOrder::class, 'billing_order_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
