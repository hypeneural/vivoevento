<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionCycle extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\SubscriptionCycleFactory
    {
        return \Database\Factories\SubscriptionCycleFactory::new();
    }

    protected $fillable = [
        'subscription_id',
        'gateway_cycle_id',
        'status',
        'billing_at',
        'period_start_at',
        'period_end_at',
        'closed_at',
        'raw_gateway_json',
    ];

    protected $casts = [
        'billing_at' => 'datetime',
        'period_start_at' => 'datetime',
        'period_end_at' => 'datetime',
        'closed_at' => 'datetime',
        'raw_gateway_json' => 'array',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'subscription_cycle_id');
    }
}
