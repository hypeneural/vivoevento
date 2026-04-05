<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventPurchase extends Model
{
    protected $fillable = [
        'organization_id',
        'client_id',
        'event_id',
        'billing_order_id',
        'plan_id',
        'package_id',
        'price_snapshot_cents',
        'currency',
        'features_snapshot_json',
        'status',
        'purchased_by_user_id',
        'purchased_at',
    ];

    protected $casts = [
        'features_snapshot_json' => 'array',
        'purchased_at' => 'datetime',
        'price_snapshot_cents' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Organizations\Models\Organization::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Events\Models\Event::class);
    }

    public function billingOrder(): BelongsTo
    {
        return $this->belongsTo(BillingOrder::class, 'billing_order_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Plans\Models\Plan::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(EventPackage::class, 'package_id');
    }

    public function purchasedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Users\Models\User::class, 'purchased_by_user_id');
    }
}
