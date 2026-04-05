<?php

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Enums\EventPackageBillingMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventPackagePrice extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\EventPackagePriceFactory
    {
        return \Database\Factories\EventPackagePriceFactory::new();
    }

    protected $fillable = [
        'event_package_id',
        'billing_mode',
        'currency',
        'amount_cents',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'billing_mode' => EventPackageBillingMode::class,
        'amount_cents' => 'integer',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function eventPackage(): BelongsTo
    {
        return $this->belongsTo(EventPackage::class);
    }
}
