<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventPackageFeature extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\EventPackageFeatureFactory
    {
        return \Database\Factories\EventPackageFeatureFactory::new();
    }

    protected $fillable = [
        'event_package_id',
        'feature_key',
        'feature_value',
    ];

    public function eventPackage(): BelongsTo
    {
        return $this->belongsTo(EventPackage::class);
    }
}
