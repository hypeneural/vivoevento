<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingGatewayEvent extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\BillingGatewayEventFactory
    {
        return \Database\Factories\BillingGatewayEventFactory::new();
    }

    protected $fillable = [
        'provider_key',
        'event_key',
        'event_type',
        'status',
        'billing_order_id',
        'gateway_order_id',
        'gateway_charge_id',
        'gateway_transaction_id',
        'occurred_at',
        'processed_at',
        'headers_json',
        'payload_json',
        'result_json',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'processed_at' => 'datetime',
        'headers_json' => 'array',
        'payload_json' => 'array',
        'result_json' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(BillingOrder::class, 'billing_order_id');
    }
}
