<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingOrderItem extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\BillingOrderItemFactory
    {
        return \Database\Factories\BillingOrderItemFactory::new();
    }

    protected $fillable = [
        'billing_order_id',
        'item_type',
        'reference_id',
        'description',
        'quantity',
        'unit_amount_cents',
        'total_amount_cents',
        'snapshot_json',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_amount_cents' => 'integer',
        'total_amount_cents' => 'integer',
        'snapshot_json' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(BillingOrder::class, 'billing_order_id');
    }

    public function eventPackage(): BelongsTo
    {
        return $this->belongsTo(EventPackage::class, 'reference_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Plans\Models\Plan::class, 'reference_id');
    }
}
